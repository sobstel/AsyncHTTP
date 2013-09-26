<?php
namespace AsyncHTTP;

use AsyncHTTP\SocketException;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Connection
{
    const NOT_CONNECTED = 'not_connected';
    const READY_TO_SEND_REQUEST = 'ready_to_send_request';
    const REQUEST_SENT = 'request_sent';
    const READY_TO_READ_RESPONSE = 'ready_to_read_response';
    const RESPONSE_READ = 'response_read';
    const CLOSED = 'closed';

    protected $id;

    protected $start_time;

    protected $socket;

    protected $status;

    protected $request;

    protected $response;

    protected $opts = [
        'timeout' => 1,
        'write_only' => true,
    ];

    protected $event_dispatcher;

    public function __construct($id, Request $request, array $opts = [])
    {
        $this->id = $id;

        $this->start_time = microtime(true);

        $this->event_dispatcher = new EventDispatcher();

        $this->request = $request;
        $this->response = new Response();

        $this->status = self::NOT_CONNECTED;

        foreach ($opts as $name => $value) {
            $this->setOption($name, $value);
        }

        $this->createSocket();
        $this->connect();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->status !== self::CLOSED) {
            // make sure it hasn't been closed in-between (async)
            if ($this->socket && (get_resource_type($this->socket) == "Socket")) {
                socket_close($this->socket);
            }
            $this->setStatus(self::CLOSED);
        }
    }

    public function observe($callable, $statuses = [])
    {
        $statuses = (array)$statuses;

        if (empty($statuses)) {
            $statuses = [
                self::NOT_CONNECTED,
                self::READY_TO_SEND_REQUEST,
                self::REQUEST_SENT,
                self::READY_TO_READ_RESPONSE,
                self::RESPONSE_READ,
                self::CLOSED
            ];
        }

        foreach ($statuses as $status) {
            $this->event_dispatcher->addListener($status, $callable);
        }
    }

    public function getId()
    {
        return $this->id;
    }

    public function getStartTime()
    {
        return $this->start_time;
    }

    public function setStatus($status)
    {
        $this->status = $status;
        $this->handleStatusChange($status);
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setOption($name, $value)
    {
        $this->opts[$name] = $value;
    }

    public function getOption($name)
    {
        if (!array_key_exists($name, $this->opts)) {
            throw new RuntimeException(sprintf("invalid option name (%s)", $name));
        }
        return $this->opts[$name];
    }

    public function isTimeoutExceeded()
    {
        return (microtime(true) - $this->start_time > $this->getOption('timeout'));
    }

    protected function createSocket()
    {
        $this->socket = socket_create($this->request->getSocketDomain(), \SOCK_STREAM, \SOL_TCP);
        if (!$this->socket) {
            $this->raiseSocketError();
        }
    }

    protected function connect()
    {
        if (!socket_set_nonblock($this->socket)) {
            $this->raiseSocketError();
        }

        $connected = @socket_connect($this->socket, $this->request->getIP(), $this->request->getPort());
        if (!$connected) {
            // http://php.net/manual/en/function.socket-connect.php#refsect1-function.socket-connect-returnvalues
            // If the socket is non-blocking then socket_connect() function returns FALSE
            // with an error "Operation now in progress".
            $in_progress = (strpos(socket_strerror(socket_last_error()), 'in progress') !== false);
            if (!$in_progress) {
                $this->raiseSocketError();
            }
        }
    }

    protected function handleStatusChange($status)
    {
        if ($status === self::READY_TO_SEND_REQUEST) {
            $success = socket_write($this->socket, $this->request->getMessage());
            if (!$success) {
                $this->raiseSocketError();
            }

            $this->dispatchEvent($status);
            $this->setStatus(self::REQUEST_SENT);

            return true;
        }

        if ($status === self::REQUEST_SENT) {
            $this->dispatchEvent($status);
            return true;
        }

        if ($status === self::READY_TO_READ_RESPONSE) {
            if ($this->getOption('write_only')) {
                $this->close();
                return true;
            }

            $message = "";
            do {
                $message_chunk = socket_read($this->socket, 8192);
                $message .= $message_chunk;
            } while ($message_chunk);

            $this->response->parseMessage($message);

            $this->dispatchEvent($status);
            $this->setStatus(self::RESPONSE_READ);

            return true;
        }

        if ($status === self::RESPONSE_READ) {
            $this->dispatchEvent($status);
            $this->close();

            return true;
        }

        if ($status === self::CLOSED) {
            $this->dispatchEvent($status);
        }

        return false;
    }

    protected function dispatchEvent($status)
    {
        $this->event_dispatcher->dispatch($status, new StatusChangeEvent($status, $this));
    }

    protected function raiseSocketError()
    {
        $errno = socket_last_error($this->socket);
        $errstr = socket_strerror($errno);
        $this->close();

        $this->response->setException(new SocketException($errstr, $errno));
    }
}

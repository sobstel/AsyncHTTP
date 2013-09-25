<?php
namespace AsyncHTTP;

use AsyncHTTP\Exception\SocketException;
use Monolog\Logger;

class Connection
{
    use Logging;

    const NOT_CONNECTED = 'not_connected';
    const READY_TO_SEND_REQUEST = 'ready_to_send_request';
    const REQUEST_SENT = 'request_sent';
    const READY_TO_READ_RESPONSE = 'ready_to_read_response';
    const RESPONSE_READ = 'response_read';
    const CLOSED = 'closed';

    protected $socket;

    protected $status;

    protected $request;

    protected $response;

    protected $write_only;

    public function __construct(Request $request, $write_only = true)
    {
        $this->request = $request;

        $this->status = self::NOT_CONNECTED;
        $this->write_only = (bool)$write_only;

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
            if (get_resource_type($this->socket) == "Socket") {
                socket_close($this->socket);
            }
            $this->setStatus(self::CLOSED);
        }
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

    protected function createSocket()
    {
        $this->socket = socket_create($this->request->getSocketDomain(), \SOCK_STREAM, \SOL_TCP);
    }

    protected function connect()
    {
        if (!socket_set_nonblock($this->socket)) {
            $this->raiseSocketError();
        }

        $connected = @socket_connect($this->socket, $this->request->getIP(), $this->request->getPort());
        if (!$connected) {
            // http://php.net/manual/en/function.socket-connect.php#refsect1-function.socket-connect-returnvalues
            // If the socket is non-blocking then socket_connect() function returns FALSE with an error Operation now in progress.
            $in_progress = (strpos(socket_strerror(socket_last_error()), 'in progress') !== false);
            if (!$in_progress) {
                $this->raiseSocketError();
            }
        }
    }

    protected function handleStatusChange($status)
    {
        $this->log(Logger::DEBUG, sprintf("change status to %s", $status));

        if ($status === self::READY_TO_SEND_REQUEST) {
            $success = socket_write($this->socket, $this->request->getMessage());
            if (!$success) {
               $this->raiseSocketError();
            }
            $this->setStatus(self::REQUEST_SENT);

            return true;
        }

        if ($status === self::READY_TO_READ_RESPONSE) {
            if ($this->write_only) {
                $this->close();
                return true;
            }

            $message = "";
            do {
                $message_chunk = socket_read($this->socket, 8192);
                $message .= $message_chunk;
            } while ($message_chunk);

            $this->response = new Response($message);
            $this->setStatus(self::RESPONSE_READ);

            return true;
        }

        if ($status === self::RESPONSE_READ) {
            $this->close();

            return true;
        }

        return false;
    }

    protected function raiseSocketError()
    {
        $errno = socket_last_error($this->socket);
        $errstr = socket_strerror($errno);
        $this->close();

        throw new SocketException($errstr, $errno);
    }
}

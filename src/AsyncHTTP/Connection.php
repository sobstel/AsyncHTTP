<?php
namespace AsyncHTTP;

use AsyncHTTP\Exception\SocketException;

class Connection
{
    const NOT_CONNECTED = 0;
    const READY_TO_SEND_REQUEST = 1;
    const REQUEST_SENT = 2;
    const READY_TO_READ_RESPONSE = 3;
    const RESPONSE_READ = 4;
    const CLOSED = 5;

    protected $request;

    protected $status;

    protected $write_only;

    protected $socket;

    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->status = self::NOT_CONNECTED;
        $this->write_only = true;

        $this->createSocket();
        $this->connect();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if ($this->status < self::CLOSED) {
            // make sure it wasn't closed in-between (async)
            if (get_resource_type($this->socket) == "Socket") {
                socket_close($this->socket);
            }
            $this->setStatus(self::CLOSED);
        }
    }

    public function setStatus($status)
    {
        $this->status = $status;
        $this->handleStatus();
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getSocket()
    {
        return $this->socket;
    }

    public function setWriteOnly($write_only = true) {
        $this->write_only = $write_only;
    }

    public function isWriteOnly()
    {
        return $this->write_only;
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

    public function handleStatus()
    {
        if ($this->status === self::READY_TO_SEND_REQUEST) {
            $success = @socket_write($this->socket, $this->request->getMessage());
            if (!$success) {
               $this->raiseSocketError();
            }
            $this->setStatus(self::REQUEST_SENT);

            return true;
        }

        if ($this->status === self::READY_TO_READ_RESPONSE) {
            if ($this->write_only) {
                $this->close();
            }

            // TODO: socket_read($this->socket)
            $this->setStatus(self::RESPONSE_READ);

            return true;
        }

        if ($this->status === self::RESPONSE_READ) {
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

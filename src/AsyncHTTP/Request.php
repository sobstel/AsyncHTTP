<?php
namespace AsyncHTTP;

use AsyncHTTP\Exception\SocketException;

class Request
{
    const GET = "GET";
    const POST = "POST";
    const HEAD = "HEAD";

    const WRITEABLE = 1;
    const READABLE = 2;
    const CLOSED = 4;

    protected $status = 0;

    protected $http_version = '1.1';

    protected $method;

    protected $host;

    protected $uri;

    protected $body;

    protected $headers = [];

    public function __construct($method, $host, $uri = "/", $ip = null, $port = 80, $socket_domain = \AF_INET)
    {
        $this->method = $method;
        $this->host = $host;
        $this->uri = $uri;

        $this->socket = socket_create($socket_domain, \SOCK_STREAM, \SOL_TCP);

        $ip = $ip ?: gethostbyname($host);
        $this->connect($ip, $port);
    }

    public function __destruct()
    {
        $this->close();
    }

    public function setBody($body)
    {
        $this->body = $body;
    }


    public function getSocket()
    {
        return $this->socket;
    }

    public function isReadyToSend()
    {
        if ($this->status & self::WRITEABLE) {
            return true;
        }

        $read = $except = null;
        $write = [$this->socket];

        $writeable = (bool)socket_select($read, $write, $except, 0);

        if ($writeable) {
            $this->status &= self::WRITEABLE;
        }

        return $writeable;
    }

    public function send()
    {
        $request = sprintf("%s %s HTTP/%s\r\n", $this->method, $this->uri, $this->http_version);
        $request .= sprintf("Host: %s\r\n", $this->host);
        $request .= "Accept: */*\r\n";
        $request .= "User-Agent: test\r\n";

        $request .= "Content-Type: text/plain\r\n";

        if (!empty($this->body)) {
            $request .= sprintf("Content-Length: %d\r\n", strlen($this->body) + 1);
            $request .= sprintf("\r\n%s\r\n", $this->body);
        }

        $request .= "\r\n";

        echo $request;

        $success = socket_write($this->socket, $request);

        if (!$success) {
            $this->raiseSocketError();
        }
    }

    public function close()
    {
        if (!($this->status & self::CLOSED)) {
            socket_close($this->socket);
            $this->status = self::CLOSED;
        }
    }

    protected function connect($ip, $port)
    {
        if (!socket_set_nonblock($this->socket)) {
            $this->raiseSocketError();
        }

        $connected = @socket_connect($this->socket, $ip, $port);
        if (!$connected) {
            // http://php.net/manual/en/function.socket-connect.php#refsect1-function.socket-connect-returnvalues
            // If the socket is non-blocking then socket_connect() function returns FALSE with an error Operation now in progress.
            $in_progress = (strpos(socket_strerror(socket_last_error()), 'in progress') !== false);
            if (!$in_progress) {
                $this->raiseSocketError();
            }
        }
    }

    protected function raiseSocketError()
    {
        $errno = socket_last_error($this->socket);
        $errstr = socket_strerror($errno);
        $this->close();

        throw new SocketException($errstr, $errno);
    }
}

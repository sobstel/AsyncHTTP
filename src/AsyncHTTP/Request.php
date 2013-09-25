<?php
namespace AsyncHTTP;

class Request
{
    const GET = "GET";
    const POST = "POST";
    const HEAD = "HEAD";

    protected $ip;

    protected $port;

    protected $socket_domain;

    protected $method;

    protected $host;

    protected $uri;

    protected $http_version = '1.1';

    protected $headers = [
        'User-Agent' => 'AsyncHTTP (https://github.com/sobstel/AsyncHTTP)',
        'Content-Type' => 'text/plain',
        'Accept' => '*/*',
    ];

    protected $body = null;

    public function __construct($method, $host, $uri = "/", $ip = null, $port = 80, $socket_domain = \AF_INET)
    {
        $this->method = $method;
        $this->host = $host;
        $this->uri = $uri;

        $this->ip = $ip ?: gethostbyname($host);
        $this->port = $port;
        $this->socket_domain = $socket_domain;
    }

    public function setHttpVersion($http_version)
    {
        $this->http_version = (string)$http_version;
    }

    public function setHeader($name, $value)
    {
        $this->headers[$name] = (string)$value;
    }

    public function setBody($body)
    {
        $this->body = (string)$body;
    }

    public function getIP()
    {
        return $this->ip;
    }

    public function getPort()
    {
        return $this->port;
    }

    public function getSocketDomain()
    {
        return $this->socket_domain;
    }

    public function getMessage()
    {
        $request_msg = sprintf("%s %s HTTP/%s\r\n", $this->method, $this->uri, $this->http_version);
        $request_msg .= sprintf("Host: %s\r\n", $this->host);

        foreach ($this->headers as $name => $value) {
            $request_msg .= sprintf("%s: %s\r\n", $name, $value);
        }

        if ($this->body !== null) {
            $request_msg .= sprintf("Content-Length: %d\r\n\r\n%s", strlen($this->body), $this->body);
        }

        $request_msg .= "\r\n";

        return $request_msg;
    }
}

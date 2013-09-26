<?php
namespace AsyncHTTP;

class Response
{
    protected $status_code;

    protected $headers = [];

    protected $body;

    /*** @var \Exception */
    protected $exception;

    /**
     * @param string Response message
     */
    public function parseMessage($message)
    {
        $lines = explode("\n", $message);

        preg_match("/^HTTP\/\d\.\d\s(\d{3})/", $lines[0], $matches);
        $this->status_code = (int)$matches[1];

        $this->headers = [];
        $this->body = null;

        for ($i = 1, $cnt = count($lines); $i < $cnt; $i += 1) {
            $line = trim($lines[$i]);

            if (empty($line)) {
                $this->body = implode("\n", array_slice($lines, $i + 1));
                break;
            }

            list($name, $value) = explode(':', $line, 2);
            $this->headers[trim($name)] = trim($value);
        }
    }

    public function getStatusCode()
    {
        return $this->status_code;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setException(\Exception $exception)
    {
        $this->exception = $exception;
    }

    public function getException()
    {
        return $this->exception;
    }

    public function hasException()
    {
        return ($this->exception !== null);
    }
}

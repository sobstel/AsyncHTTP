<?php
namespace AsyncHTTP;

class Response
{
    protected $status_code;

    protected $headers;

    protected $body;

    /**
     * @param string Response message
     */
    public function __construct($message)
    {
        $lines = explode("\n", $message);

        preg_match("/^HTTP\/\d\.\d\s(\d{3})/", $lines[0], $matches);
        $status_code = (int)$matches[1];

        $headers = [];
        $body = null;

        for ($i = 1, $cnt = count($lines); $i < $cnt; $i += 1) {
            $line = trim($lines[$i]);

            if (empty($line)) {
                $body = implode("\n", array_slice($lines, $i + 1));
                break;
            }

            list($name, $value) = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        $this->status_code = $status_code;
        $this->headers = $headers;
        $this->body = $body;
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
}

<?php
namespace AsyncHTTP;

class Response
{
    /*** var @int HTTP status code */
    protected $status_code;

    /*** @var array */
    protected $headers = [];

    /*** @var string */
    protected $body;

    /*** @var \Exception */
    protected $exception;

    /**
     * @param string Response message
     */
    public function parseMessage($message)
    {
        $lines = explode("\n", $message);

        $lines_num = count($lines);
        if ($lines_num == 0) {
            return false;
        }

        preg_match("/^HTTP\/\d\.\d\s(\d{3})/", $lines[0], $matches);
        if (!isset($matches[1])) {
            return false;
        }

        $this->status_code = (int)$matches[1];

        $this->headers = [];
        $this->body = null;

        for ($i = 1; $i < $lines_num; $i += 1) {
            $line = trim($lines[$i]);

            if (empty($line)) {
                $this->body = implode("\n", array_slice($lines, $i + 1));
                break;
            }

            if (strpos($line, ":") !== false) {
                list($name, $value) = explode(':', $line, 2);
                $this->headers[trim($name)] = trim($value);
            }
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

<?php
namespace AsyncHTTP;

use Monolog\Logger;

trait Logging
{
    protected $logger;

    protected $log_message_prefix;

    public function enableLogging(Logger $logger, $log_message_prefix)
    {
        $this->logger = $logger;
        $this->log_message_prefix = $log_message_prefix;
    }

    public function log($level, $message, array $context = [])
    {
        if ($this->logger) {
            if ($this->log_message_prefix) {
                $message = sprintf("%s: %s", $this->log_message_prefix, $message);
            }

            $this->logger->addRecord($level, $message, $context);
        }
    }
}

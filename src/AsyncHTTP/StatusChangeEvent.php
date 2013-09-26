<?php
namespace AsyncHTTP;

use Symfony\Component\EventDispatcher\Event;

class StatusChangeEvent extends Event
{
    protected $status;

    protected $connection;

    public function __construct($status, Connection $connection)
    {
        $this->status = $status;
        $this->connection = $connection;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}

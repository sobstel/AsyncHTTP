<?php
namespace AsyncHTTP;

use AsyncHTTP\Connection;
use AsyncHTTP\Exception\SocketException;

class ConnectionPool implements \IteratorAggregate
{
    protected $connections = [];

    public function create($id, Request $request)
    {
        $this->set($id, new Connection($request));
    }

    public function set($id, Connection $connection)
    {
        $this->connections[$id] = $connection;
    }

    public function get($id)
    {
        return $this->connections[$id];
    }

    // execute once
    public function process()
    {
        $writeable_sockets = $readable_sockets = [];
        $except = null;

        foreach ($this->connections as $connection) {
            if ($connection->getStatus() === Connection::NOT_CONNECTED) {
                $writeable_sockets[] = $connection->getSocket();
            }
            if ($connection->getStatus() === Connection::REQUEST_SENT) {
                $readable_sockets[] = $connection->getSocket();
            }
        }

        if (empty($writeable_sockets) && empty($readable_sockets)) {
            return false;
        }

        $sockets_num = socket_select($readable_sockets, $writeable_sockets, $except, 0);

        if ($sockets_num === false) {
            $errno = socket_last_error();
            throw new SocketException(socket_strerror($errno), $errno);
        }

        if ($sockets_num === 0) {
            return false;
        }

        foreach ($this->connections as $connection) {
            if (array_search($connection->getSocket(), $writeable_sockets, true) !== false) {
                $connection->setStatus(Connection::READY_TO_SEND_REQUEST);
            }
            if (array_search($connection->getSocket(), $readable_sockets, true) !== false) {
                $connection->setStatus(Connection::READY_TO_READ_RESPONSE);
            }
        }
    }

    public function force()
    {
        $left_to_process = count($this->connections);

        do {
            foreach ($this->connections as $connection) {
                $this->process();
                if ($connection->getStatus() === Connection::CLOSED) {
                    $left_to_process =- 1;
                }
            }
        } while ($left_to_process > 0);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->connections);
    }
}

<?php
namespace AsyncHTTP;

use AsyncHTTP\Connection;
use AsyncHTTP\Exception\SocketException;
use Monolog\Logger;

class ConnectionPool implements \IteratorAggregate
{
    use Logging;

    protected $connections = [];

    public function create($id, Request $request, $write_only = true)
    {
        $this->set($id, new Connection($request, $write_only));
    }

    public function set($id, Connection $connection)
    {
        if ($this->logger) {
            $connection->enableLogging($this->logger, sprintf('conn (%s)', $id));
        }

        $this->connections[$id] = $connection;
    }

    public function get($id)
    {
        return $this->connections[$id];
    }

    public function poke()
    {
        $writeable_sockets = $readable_sockets = [];
        $except = null;

        foreach ($this->connections as $id => $connection) {
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

    public function pokeUntilClosed()
    {
        $connections = $this->connections;

        do {
            foreach ($connections as $id => $connection) {
                $this->poke();
                if ($connection->getStatus() === Connection::CLOSED) {
                    unset($connections[$id]);
                }
            }
        } while (count($connections) > 0);
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->connections);
    }
}

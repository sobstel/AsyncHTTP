<?php
namespace AsyncHTTP;

use ArrayIterator;
use AsyncHTTP\Connection;
use AsyncHTTP\SocketException;
use AsyncHTTP\TimeoutException;
use IteratorAggregate;
use RuntimeException;

class ConnectionPool implements IteratorAggregate
{
    protected $connections = [];

    public function create($id, Request $request, array $opts = [])
    {
        $this->add(new Connection($id, $request, $opts));
        return $this->get($id);
    }

    public function add(Connection $connection)
    {
        $this->connections[$connection->getId()] = $connection;
    }

    public function get($id)
    {
        if (!array_key_exists($id, $this->connections)) {
            throw new RuntimeException(sprintf("%s connection does not exist"));
        }
        return $this->connections[$id];
    }

    public function poke()
    {
        $writeable_sockets = $readable_sockets = [];
        $except = null;

        foreach ($this->connections as $id => $connection) {
            if ($connection->isTimeoutExceeded()) {
                $connection->getResponse()->setException(new TimeoutException("connection timeout exceeded"));
                $connection->close();
            }
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

    public function observe($callable, $ids = [], $statuses = [])
    {
        $ids = (array)$ids;

        if (empty($ids)) {
            $ids = array_keys($this->connections);
        }

        foreach ($ids as $id) {
            $this->get($id)->observe($callable, $statuses);
        }
    }

    public function getIterator()
    {
        return new ArrayIterator($this->connections);
    }
}

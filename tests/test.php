<?php
require __DIR__.'/../vendor/autoload.php';

use AsyncHTTP\Exception\SocketException;
use AsyncHTTP\Request;
use AsyncHTTP\ConnectionPool;
use AsyncHTTP\Connection;
use Monolog\Logger;

try {
    $start = microtime(true);
    $request = new Request(Request::POST, 'logs-01.loggly.com', '/inputs/2c7d3caf-bc81-4981-939f-7d0f5e2fbf74/tag/test/');
    $request->setBody(json_encode([
        'type' => 'test',
        'desc' => 'multi tests, has it actually arrived?',
        'rand' => mt_rand()
    ]));

    $pool = new ConnectionPool();

    $pool->create('loggly', $request, ['write_only' => true]);
    $pool->create('sobstel', new Request(Request::GET, 'sobstel.org', '/'), ['write_only' => true]);
    $pool->create('example', new Request(Request::GET, 'example.org', '/'), ['write_only' => true]);

    $logger = new Logger('asynchttp');
    $pool->observe(function($event) use ($logger) {
        $logger->log(Logger::DEBUG, sprintf("%s: %s", $event->getConnection()->getId(), $event->getStatus()));
    });

    $pool->pokeUntilClosed();

    foreach ($pool as $id => $conn) {
        var_dump($id, $conn->getResponse());
    }
} catch (SocketException $e) {
    echo sprintf("[%d] %s\r\n", $e->getCode(), $e->getMessage());
    echo $e->getTraceAsString();
}

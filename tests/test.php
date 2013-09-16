<?php

require_once __DIR__.'/../src/AsyncHTTP/Exception/SocketException.php';

require_once __DIR__.'/../src/AsyncHTTP/Request.php';
require_once __DIR__.'/../src/AsyncHTTP/Response.php';
require_once __DIR__.'/../src/AsyncHTTP/Connection.php';
require_once __DIR__.'/../src/AsyncHTTP/ConnectionPool.php';

use AsyncHTTP\Exception\SocketException;
use AsyncHTTP\Request;
use AsyncHTTP\ConnectionPool;
use AsyncHTTP\Connection;

try {
    $start = microtime(true);
    $request = new Request(Request::POST, 'logs-01.loggly.com', '/inputs/2c7d3caf-bc81-4981-939f-7d0f5e2fbf74/tag/test/');
    $request->setBody(json_encode([
        'type' => 'error',
        'val' => 2,
        'desc' => 'test value',
        'rand' => mt_rand()
    ]));

    $id = 'loggly';
    $pool = new ConnectionPool();
    $pool->create($id, $request);
    $pool->force();

} catch (SocketException $e) {
    echo sprintf("[%d] %s\r\n", $e->getCode(), $e->getMessage());
    echo $e->getTraceAsString();
}

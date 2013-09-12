<?php

require_once __DIR__.'/../src/AsyncHTTP/Exception/SocketException.php';

require_once __DIR__.'/../src/AsyncHTTP/Request.php';
require_once __DIR__.'/../src/AsyncHTTP/RequestPool.php';

use AsyncHTTP\Exception\SocketException;
use AsyncHTTP\Request;

try {
    $req = new Request(Request::POST, 'logs-01.loggly.com', '/inputs/2c7d3caf-bc81-4981-939f-7d0f5e2fbf74/tag/test/');
    $req->setBody(mt_rand());
    do {} while (!$req->isReadyToSend());
    $req->send();

} catch (SocketException $e) {
    echo sprintf("[%d] %s\r\n", $e->getCode(), $e->getMessage());
    echo $e->getTraceAsString();
}

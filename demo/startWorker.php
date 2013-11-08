<?php

require_once '../lib/Qless/Worker.php';
require_once '../lib/Qless/Queue.php';
require_once '../lib/Qless/Client.php';
require_once 'TestWorkerImpl.php';

$queues = ['testQueue1','testQueue2'];
$REDIS_HOST = getenv('REDIS_HOST') ?: 'localhost';
$REDIS_PORT = getenv('REDIS_PORT') ?: 6380;

$client = new Qless\Client($REDIS_HOST, $REDIS_PORT);
$worker = new Qless\Worker("TestWorker_1", $queues, $client, 5);

$worker->run();

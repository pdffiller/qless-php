<?php

require_once '../lib/Qless/Worker.php';
require_once '../lib/Qless/Queue.php';
require_once '../lib/Qless/Client.php';
require_once 'TestWorkerImpl.php';

$queues = ['testQueue1','testQueue2'];
$client = new Qless\Client('localhost',6380);
$worker = new Qless\Worker("TestWorker_1", $queues, $client, 5);

$worker->run();

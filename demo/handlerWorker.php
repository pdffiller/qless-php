<?php

use Qless\Client;
use Qless\Worker;
use Qless\Demo\JobHandler;

require_once '../vendor/autoload.php';
require_once './bootstrap.php';

$queues = ['testQueue1', 'testQueue2'];
$client = new Client(REDIS_HOST, REDIS_PORT);

$worker = new Worker("WorkerTest_1", $queues, $client, 5);
$worker->registerJobPerformHandler(JobHandler::class);

$worker->run();

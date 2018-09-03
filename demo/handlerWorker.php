<?php

use Qless\Client;
use Qless\Worker;
use Qless\Demo\JobHandler;

require_once __DIR__ . '/../tests/bootstrap.php';

$queues = ['testQueue1', 'testQueue2'];
$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);

$worker = new Worker("WorkerTest_1", $queues, $client, 5);
$worker->registerJobPerformHandler(JobHandler::class);

$worker->run();

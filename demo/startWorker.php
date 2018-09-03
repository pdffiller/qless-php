<?php

use Qless\Client;
use Qless\Worker;

require_once __DIR__ . '/../tests/bootstrap.php';

$queues = ['testQueue1', 'testQueue2'];

$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
$worker = new Worker("TestWorker_1", $queues, $client, 5);

$worker->run();

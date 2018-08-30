<?php

use Qless\Client;
use Qless\Queue;

require_once '../vendor/autoload.php';
require_once './bootstrap.php';

$client = new Client(REDIS_HOST, REDIS_PORT);
$queue = new Queue('testQueue1', $client);

$testData1 = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
$testData2 = ['performMethod' => 'exitMethod', 'payload' => 'otherData'];

$ret = $queue->put(TestWorkerImpl::class, 'jobTestDEF', $testData1);
if ($ret) {
    echo 'successfully put on queue.', PHP_EOL;
} else {
    echo 'failed put on queue.', PHP_EOL;
}

$ret = $queue->put(TestWorkerImpl::class, 'jobTestGHI', $testData2);
if ($ret) {
    echo 'successfully put on queue.', PHP_EOL;
} else {
    echo 'failed put on queue.', PHP_EOL;
}


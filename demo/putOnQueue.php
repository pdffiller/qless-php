<?php

use Qless\Client;
use Qless\Queue;
use Qless\Demo\Worker;

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/bootstrap.php';

$client = new Client(REDIS_HOST, REDIS_PORT);
$queue = new Queue('testQueue1', $client);

$testData1 = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
$testData2 = ['performMethod' => 'exitMethod', 'payload' => 'otherData'];

$ret = $queue->put(Worker::class, 'jobTestDEF', $testData1);
if ($ret) {
    echo 'successfully put on queue.', PHP_EOL;
} else {
    echo 'failed put on queue.', PHP_EOL;
}

$ret = $queue->put(Worker::class, 'jobTestGHI', $testData2);
if ($ret) {
    echo 'successfully put on queue.', PHP_EOL;
} else {
    echo 'failed put on queue.', PHP_EOL;
}


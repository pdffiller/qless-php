<?php

use Qless\Client;
use Qless\Queue;
use Qless\Demo\Worker;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . '/bootstrap.php';

$logger = new Logger('APP');
$logger->pushHandler(new StreamHandler(STDOUT, Logger::DEBUG));

$client = new Client(REDIS_HOST, REDIS_PORT);
$queue = new Queue('testQueue1', $client);

$payload = ['payload' => 'otherData'];

$testData1 = ['performMethod' => 'myPerformMethod'];
$testData2 = ['performMethod' => 'exitMethod'];
$testData3 = ['performMethod' => 'myThrowMethod'];

if ($queue->put(Worker::class, 'jobTest-1', $testData1 + $payload)) {
    $logger->debug('Successfully put on queue the job with ID: jobTest-1.');
} else {
    $logger->error('Failed put on queue.');
}

if ($queue->put(Worker::class, 'jobTest-2', $testData2 + $payload)) {
    $logger->debug('Successfully put on queue the job with ID: jobTest-2.');
} else {
    $logger->error('Failed put on queue.');
}

if ($queue->put(Worker::class, 'jobTest-3', $testData3 + $payload)) {
    $logger->debug('Successfully put on queue the job with ID: jobTest-3.');
} else {
    $logger->error('Failed put on queue.');
}

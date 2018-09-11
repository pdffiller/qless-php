<?php

use Qless\Client;
use Qless\Queue;
use Qless\Demo\Worker;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require_once __DIR__ . '/../tests/bootstrap.php';

$logger = new Logger('APP');
$logger->pushHandler(new StreamHandler(STDOUT, Logger::DEBUG));

$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
$queue = new Queue('testQueue1', $client);

$payload = ['payload' => 'otherData'];

$testData1 = ['performMethod' => 'myPerformMethod'];
$testData2 = ['performMethod' => 'exitMethod'];
$testData3 = ['performMethod' => 'myThrowMethod'];

if ($queue->put(Worker::class, $testData1 + $payload)) {
    $logger->debug('Successfully put on queue the job.');
} else {
    $logger->error('Failed put on queue.');
}

if ($queue->put(Worker::class, $testData2 + $payload)) {
    $logger->debug('Successfully put on queue the job.');
} else {
    $logger->error('Failed put on queue.');
}

if ($queue->put(Worker::class, $testData3 + $payload)) {
    $logger->debug('Successfully put on queue the job.');
} else {
    $logger->error('Failed put on queue.');
}

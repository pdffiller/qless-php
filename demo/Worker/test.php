<?php

use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Qless\Client;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Queue;
use Qless\Workers\ForkingWorker;

require_once __DIR__ . '/../../tests/bootstrap.php';

// Create a client
$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);

// Get the queues you use
$queues = array_map(function (string $name) use ($client) {
    return new Queue($name, $client);
}, ['testing', 'testing-2', 'testing-3']);

/// Create a job reserver; different reservers use different
// strategies for which order jobs are popped off of queues
$reserver = new OrderedReserver($queues);

// Create internal logger for debugging purposes
$logger = new Logger('APP');
$logger->pushHandler(new ErrorLogHandler());

$worker = new ForkingWorker(
    $reserver,
    $client,
    $logger
);

$worker->run();

<?php

use Qless\Client;
use Qless\Worker;
use Qless\Queue;
use Qless\Demo\JobHandler;
use Qless\Jobs\Reservers\OrderedReserver;

require_once __DIR__ . '/../tests/bootstrap.php';

$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);

$queues = array_map(function (string $name) use ($client) {
    return new Queue($name, $client);
}, ['test-queue-1', 'test-queue-2']);

$reserver = new OrderedReserver($queues);

$worker = new Worker($reserver, $client);

$worker->setInterval(5);
$worker->registerJobPerformHandler(JobHandler::class);
$worker->run();

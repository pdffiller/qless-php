<?php

use Qless\Client;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Queue;
use Qless\Workers\ForkingWorker;

require_once __DIR__ . '/../tests/bootstrap.php';

$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);

$worker = new ForkingWorker(
    new OrderedReserver(
        array_map(function (string $name) use ($client) {
            return new Queue($name, $client);
        }, ['test-queue-1', 'test-queue-2'])
    ),
    $client
);

$worker->setInterval(5);
$worker->run();

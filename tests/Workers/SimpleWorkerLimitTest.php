<?php

namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Workers\ResourceLimitedWorkerInterface;
use Qless\Workers\SimpleWorker;

class SimpleWorkerLimitTest extends WorkerLimitTest
{
    protected function getWorker(): ResourceLimitedWorkerInterface
    {
        return new SimpleWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

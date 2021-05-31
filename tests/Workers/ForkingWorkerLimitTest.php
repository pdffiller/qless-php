<?php
namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Workers\ResourceLimitedWorkerInterface;
use Qless\Workers\ForkingWorker;

class ForkingWorkerLimitTest extends WorkerLimitTest
{
    protected function getWorker(): ResourceLimitedWorkerInterface
    {
        return new ForkingWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

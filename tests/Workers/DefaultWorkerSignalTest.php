<?php

namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\AbstractReserver;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Tests\Stubs\DefaultSignalWorker;
use Qless\Workers\AbstractWorker;
use Qless\Workers\ResourceLimitedWorkerInterface;
use Qless\Workers\SimpleWorker;

class DefaultWorkerSignalTest extends WorkerSignalTest
{
    protected function getWorker(): DefaultSignalWorker
    {
        return new DefaultSignalWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

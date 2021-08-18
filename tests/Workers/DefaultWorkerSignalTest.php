<?php

namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Tests\Stubs\DefaultSignalWorker;
use Qless\Tests\Stubs\SignalWorker;

class DefaultWorkerSignalTest extends WorkerSignalTest
{

    public function signalDataProvider(): array
    {
        return [
            [\SIGTERM, 'shutdownNow'],
            [\SIGINT, 'shutdownNow'],
            [\SIGQUIT, 'shutdown'],
            [\SIGUSR1, 'killChildren'],
            [\SIGUSR2, 'pauseProcessing'],
            [\SIGCONT, 'unPauseProcessing']
        ];
    }

    protected function getWorker(): SignalWorker
    {
        return new DefaultSignalWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

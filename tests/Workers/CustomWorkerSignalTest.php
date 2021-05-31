<?php

namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Tests\Stubs\CustomSignalWorker;
use Qless\Tests\Stubs\SignalWorker;

class CustomWorkerSignalTest extends WorkerSignalTest
{

    public function signalDataProvider(): array
    {
        return [
            [\SIGTERM, 'doSigTerm'],
            [\SIGINT, 'doSigInt'],
            [\SIGQUIT, 'doSigQuit'],
            [\SIGUSR1, 'doSigUsr1'],
            [\SIGUSR2, 'doSigUsr2'],
            [\SIGCONT, 'doSigCont']
        ];
    }

    protected function getWorker(): SignalWorker
    {
        return new CustomSignalWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

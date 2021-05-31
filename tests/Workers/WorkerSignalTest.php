<?php

namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\AbstractReserver;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Tests\QlessTestCase;
use Qless\Signals\SignalHandler;
use Qless\Tests\Stubs\DefaultSignalWorker;
use Qless\Tests\Stubs\SignalWorker;
use Qless\Workers\AbstractWorker;
use Qless\Workers\WorkerInterface;

/**
 * Qless\Tests\Workers\WorkerSignalTest
 *
 * @package Qless\Tests\Workers
 */
abstract class WorkerSignalTest extends QlessTestCase
{

    abstract protected function getWorker(): SignalWorker;

    /**
     * @test
     * @dataProvider signalDataProvider
     *
     * @param int    $signal
     * @param string $expected
     */
    public function shouldGetSignalName(int $signal, string $expected): void
    {
        $worker = $this->getWorker();

        \posix_kill(\posix_getpid(), $signal);

        self::assertEquals($expected, $worker->getLastSignalAction());
    }

    public function signalDataProvider(): array
    {
        return [
            [\SIGTERM, 'shutDownNow'],
            [\SIGINT, 'shutDownNow'],
            [\SIGQUIT, 'shutdown'],
            [\SIGUSR1, 'killChildren'],
            [\SIGUSR2, 'pauseProcessing'],
            [\SIGCONT, 'unPauseProcessing']
        ];
    }
}

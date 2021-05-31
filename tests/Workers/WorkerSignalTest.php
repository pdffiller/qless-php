<?php

namespace Qless\Tests\Workers;

use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\SignalWorker;

/**
 * Qless\Tests\Workers\WorkerSignalTest
 *
 * @package Qless\Tests\Workers
 */
abstract class WorkerSignalTest extends QlessTestCase
{

    abstract protected function getWorker(): SignalWorker;

    abstract public function signalDataProvider(): array;

    /**
     * @test
     * @dataProvider signalDataProvider
     *
     * @param int $signal
     * @param string $expected
     */
    public function shouldHandleSignalAction(int $signal, string $expected): void
    {
        $worker = $this->getWorker();

        $worker->run();

        \posix_kill(\posix_getpid(), $signal);

        self::assertEquals($expected, $worker->getLastSignalAction());
    }
}

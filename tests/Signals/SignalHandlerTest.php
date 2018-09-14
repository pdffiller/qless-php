<?php

namespace Qless\Tests\Signals;

use Qless\Tests\QlessTestCase;
use Qless\Signals\SignalHandler;

/**
 * Qless\Tests\Signals\SignalHandlerTest
 *
 * @package Qless\Tests\Signals
 */
class SignalHandlerTest extends QlessTestCase
{
    /**
     * @test
     * @dataProvider signalDataProvider
     *
     * @param int    $signal
     * @param string $expected
     */
    public function shouldGetSignalName(int $signal, string $expected)
    {
        $this->assertEquals($expected, SignalHandler::sigName($signal));
    }

    public function signalDataProvider(): array
    {
        return [
            [300,     'UNKNOWN'],
            [SIGUSR1, 'SIGUSR1'],
            [SIGBUS,  'SIGBUS'],
            [SIGXCPU, 'SIGXCPU'],
            [1,       'SIGHUP' ],
            [-999999, 'UNKNOWN'],
        ];
    }
}

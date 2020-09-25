<?php

namespace Qless\Tests\Events;

use Qless\Subscribers\WatchdogSubscriber;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Support\RedisAwareTrait;
use Qless\SystemFacade;

/**
 * Qless\Tests\Events\WatchdogSubscriberTest
 *
 * @package Qless\Tests
 */
class WatchdogSubscriberTest extends QlessTestCase
{
    use RedisAwareTrait;

    /** @test */
    public function shouldReceiveExpectedMessages(): void
    {
        $system = $this->createMock(SystemFacade::class);

        $system
            ->expects(self::atLeast(1))
            ->method('posixKill')
            ->with(1, SIGKILL)
            ->willReturn(true);

        $listener = new WatchdogSubscriber(
            $this->redis(true),
            ['test:chan-1', 'test:chan-2'],
            $system
        );

        $publisher = realpath(__DIR__ . '/../publish.php');
        exec(escapeshellcmd("php '{$publisher}'") . " > {$publisher}.log 2>&1 &");

        $listener->watchdog('jid-1', 'test-worker', 1);
        self::assertTrue(true);
    }
}

<?php

namespace Qless\Tests\Events;

use Qless\Events\QlessCoreEvent;
use Qless\Events\Subscriber;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Support\RedisAwareTrait;

/**
 * Qless\Tests\Events\QlessCoreEventTest
 *
 * @package Qless\Tests
 */
class QlessCoreEventTest extends QlessTestCase
{
    use RedisAwareTrait;

    /** @test */
    public function shouldReceiveExpectedMessages()
    {
        $events = [];
        $time   = time();

        $listener = new Subscriber($this->redis(), ['chan-1', 'chan-2']);

        $callback = function ($channel, $event) use ($listener, $time, &$events) {
            if (time() - $time > 3 || count($events) > 3) {
                $listener->stop();
                return false;
            }

            $events[] = [$channel => $event];

            return true;
        };

        $publisher = realpath(__DIR__ . '/../publish.php');
        exec(escapeshellcmd("php '{$publisher}'") . ' > /dev/null 2>&1 &');

        $listener->messages($callback);

        $this->assertEquals([
            ['chan-1' => new QlessCoreEvent(QlessCoreEvent::CANCELED)],
            ['chan-1' => new QlessCoreEvent(QlessCoreEvent::COMPLETED)],
            ['chan-2' => new QlessCoreEvent(QlessCoreEvent::FAILED)],
            ['chan-2' => new QlessCoreEvent(QlessCoreEvent::LOCK_LOST)],
        ], $events);
    }
}

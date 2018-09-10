<?php

namespace Qless\Tests;

use Qless\Events\Event;
use Qless\Events\Subscriber;
use Qless\Tests\Support\RedisAwareTrait;

/**
 * Qless\Tests\ListenerTest
 *
 * @package Qless\Tests
 */
class ListenerTest extends QlessTestCase
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

        exec(escapeshellcmd('php ' . __DIR__ . '/publish.php') . ' > /dev/null 2>&1 &');

        $listener->messages($callback);

        $this->assertEquals([
            ['chan-1' => new Event(Event::CANCELED)],
            ['chan-1' => new Event(Event::COMPLETED)],
            ['chan-2' => new Event(Event::FAILED)],
            ['chan-2' => new Event(Event::LOCK_LOST)],
        ], $events);
    }
}

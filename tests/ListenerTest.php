<?php

namespace Qless\Tests;

use Qless\Subscriber;
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

        $event1 = new \stdClass();
        $event1->event = 'event #1 to channel 1';

        $event2 = new \stdClass();
        $event2->event = 'event #2 to channel 1';

        $event3 = new \stdClass();
        $event3->event = 'event #3 to channel 2';

        $event4 = new \stdClass();
        $event4->event = 'event #4 to channel 2';

        $this->assertEquals([
            ['chan-1' => $event1],
            ['chan-1' => $event2],
            ['chan-2' => $event3],
            ['chan-2' => $event4],
        ], $events);
    }
}

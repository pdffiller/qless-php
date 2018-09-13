<?php

namespace Qless\Tests\Reservers;

use Qless\Jobs\Reservers\ShuffledRoundRobinReserver;
use Qless\Queue;

/**
 * Qless\Tests\Reservers\ShuffledRoundRobinReserver
 *
 * @package Qless\Tests\Reservers
 */
class ShuffledRoundRobinReserverTest extends RoundRobinReserverTest
{
    /** @test */
    public function shouldReturnNulForNoQueues()
    {
        $reserver = new ShuffledRoundRobinReserver([]);

        $this->assertEquals([], $reserver->getQueues());
        $this->assertNull($reserver->reserve());
    }

    /** @test */
    public function shouldNormalConstructObjectWithQueuesStack()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $stack = [$queue1, $queue2];

        $reserver = new ShuffledRoundRobinReserver($stack);

        $this->assertEquals($stack, $reserver->getQueues());
    }

    /** @test */
    public function shouldGetDescription()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $reserver = new ShuffledRoundRobinReserver([$queue1, $queue2]);

        $this->assertEquals('queue-1, queue-2 (shuffled round robin)', $reserver->getDescription());
    }

    /** @test */
    public function shouldShuffleQueuesBeforeFork()
    {
        $reserver = new ShuffledRoundRobinReserver(
            [
                new Queue('queue-1', $this->client),
                new Queue('queue-2', $this->client),
                new Queue('queue-3', $this->client),
                new Queue('queue-4', $this->client),
                new Queue('queue-5', $this->client),
            ]
        );

        $reserver->beforeFork();

        $this->assertRegExp(
            '#queue-\d, queue-\d, queue-\d, queue-\d, queue-\d \(shuffled round robin\)#',
            $reserver->getDescription()
        );

        $this->assertCount(5, $reserver->getQueues());
        $this->assertContainsOnly(Queue::class, $reserver->getQueues());
    }
}

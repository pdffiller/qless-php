<?php

namespace Qless\Tests\Jobs\Reservers;

use Qless\Exceptions\InvalidArgumentException;
use Qless\Jobs\Reservers\ShuffledRoundRobinReserver;
use Qless\Queues\Queue;

/**
 * Qless\Tests\Jobs\Reservers\ShuffledRoundRobinReserver
 *
 * @package Qless\Tests\Jobs\Reservers
 */
class ShuffledRoundRobinReserverTest extends RoundRobinReserverTest
{

    /**
     * @test
     *
     */
    public function shouldThrowExceptionForNoQueuesAndSpec(): void
    {
        $this->expectExceptionMessage("A queues list or a specification to reserve queues are required.");
        $this->expectException(InvalidArgumentException::class);
        new ShuffledRoundRobinReserver($this->client->queues, []);
    }

    /** @test
     */
    public function shouldNormalConstructObjectWithQueuesStack(): void
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $stack = [$queue1, $queue2];

        $reserver = new ShuffledRoundRobinReserver($this->client->queues, ['queue-1', 'queue-2']);

        self::assertEquals($stack, $reserver->getQueues());
    }

    /** @test
     */
    public function shouldGetDescription(): void
    {
        $reserver = new ShuffledRoundRobinReserver($this->client->queues, ['queue-1', 'queue-2']);

        self::assertEquals('queue-1, queue-2 (shuffled round robin)', $reserver->getDescription());
    }

    /** @test */
    public function shouldShuffleQueuesBeforeWork(): void
    {
        $reserver = new ShuffledRoundRobinReserver(
            $this->client->queues,
            ['queue-1', 'queue-2', 'queue-3', 'queue-4', 'queue-5']
        );

        $reserver->beforework();

        self::assertRegExp(
            '#queue-\d, queue-\d, queue-\d, queue-\d, queue-\d \(shuffled round robin\)#',
            $reserver->getDescription()
        );

        self::assertCount(5, $reserver->getQueues());
        self::assertContainsOnly(Queue::class, $reserver->getQueues());
    }
}

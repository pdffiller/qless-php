<?php

namespace Qless\Tests\Jobs\Reservers;

use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\RoundRobinReserver;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Jobs\Reservers\RoundRobinReserverTest
 *
 * @package Qless\Tests\Jobs\Reservers
 */
class RoundRobinReserverTest extends QlessTestCase
{
    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage A queues list or a specification to reserve queues are required.
     */
    public function shouldThrowExceptionForNoQueuesAndSpec()
    {
        new RoundRobinReserver($this->client->queues, []);
    }

    /** @test */
    public function shouldReserveJob()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);
        $queue3 = new Queue('queue-3', $this->client);

        $_SERVER['performed'] = '';

        $class = new class {
            public function perform(BaseJob $job): void
            {
                $_SERVER['performed'] .= "{$job->data[0]}";
            }
        };

        $queue1->put(get_class($class), ['baz']);
        $queue2->put(get_class($class), ['bar-']);
        $queue3->put(get_class($class), ['foo-']);

        $reserver = new RoundRobinReserver(
            $this->client->queues,
            ['queue-3', 'queue-2', 'queue-1']
        );

        $reserver->reserve()->perform();
        $reserver->reserve()->perform();
        $reserver->reserve()->perform();

        $this->assertEquals('foo-bar-baz', $_SERVER['performed']);
    }

    /** @test */
    public function shouldNormalConstructObjectWithQueuesStack()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $stack = [$queue1, $queue2];

        $reserver = new RoundRobinReserver($this->client->queues, ['queue-1', 'queue-2']);

        $this->assertEquals($stack, $reserver->getQueues());
    }

    /** @test */
    public function shouldGetDescription()
    {
        $reserver = new RoundRobinReserver($this->client->queues, ['queue-1', 'queue-2']);

        $this->assertEquals('queue-1, queue-2 (round robin)', $reserver->getDescription());
    }

    /** @test */
    public function shouldGetNullOnEmptyQueue()
    {
        $reserver = new RoundRobinReserver($this->client->queues, ['queue-1', 'queue-2']);

        $this->assertNull($reserver->reserve());
    }
}

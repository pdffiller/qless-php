<?php

namespace Qless\Tests\Jobs\Reservers;

use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\PriorityReserver;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;

/**
 * Class PriorityReserverTest
 *
 * @package Qless\Tests\Jobs\Reservers
 */
class PriorityReserverTest extends QlessTestCase
{
    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage A queues list or a specification to reserve queues are required.
     */
    public function shouldThrowExceptionForNoQueuesAndSpec()
    {
        new PriorityReserver($this->client->queues, []);
    }

    /** @test */
    public function shouldReserveJob()
    {
        $queue1 = new Queue('queue-1-', $this->client);
        $queue2 = new Queue('queue-2-', $this->client);
        $queue3 = new Queue('queue-3', $this->client);
        $queue4 = new Queue('queue-4-', $this->client);

        $_SERVER['performed'] = '';

        $class = new class {
            public function perform(BaseJob $job): void
            {
                $_SERVER['performed'].= $job->getQueue();
                $job->complete();
            }
        };

        $queue1->put(get_class($class), []);
        $queue2->put(get_class($class), []);
        $queue3->put(get_class($class), []);
        $queue4->put(get_class($class), []);

        $reserver = new PriorityReserver(
            $this->client->queues,
            ['queue-3', 'queue-2-', 'queue-1-', 'queue-4-']
        );

        $reserver->setPriorities([
            'queue-2-' => 10,
            'queue-3' => 3,
            'queue-1-' => 5,
            'queue-4-' => 4,
        ]);

        $reserver->reserve()->perform();
        $reserver->reserve()->perform();
        $reserver->reserve()->perform();
        $reserver->reserve()->perform();

        $this->assertEquals('queue-2-queue-1-queue-4-queue-3', $_SERVER['performed']);
    }

    /** @test */
    public function shouldNormalConstructObjectWithQueuesStack()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $stack = [$queue1, $queue2];

        $reserver = new PriorityReserver($this->client->queues, ['queue-1', 'queue-2']);

        $this->assertEquals($stack, $reserver->getQueues());
    }

    /** @test */
    public function shouldGetDescription()
    {
        $reserver = new PriorityReserver($this->client->queues, ['queue-1', 'queue-2']);

        $this->assertEquals('queue-1, queue-2 (priority)', $reserver->getDescription());
    }

    /** @test */
    public function shouldOrderByPriorityQueuesBeforeWork()
    {
        $reserver = new PriorityReserver(
            $this->client->queues,
            ['queue-1', 'queue-3', 'queue-2']
        );

        $reserver->setPriorities([
            'queue-1' => 1,
            'queue-3' => 10,
            'queue-2' => 5,
        ]);

        $reserver->beforework();

        $this->assertRegExp(
            '#queue-3, queue-2, queue-1 \(priority\)#',
            $reserver->getDescription()
        );

        $this->assertCount(3, $reserver->getQueues());
        $this->assertContainsOnly(Queue::class, $reserver->getQueues());
    }

    /** @test */
    public function shouldRangeByPriorityQueuesBeforeWork()
    {
        $reserver = new PriorityReserver(
            $this->client->queues,
            ['queue-1', 'queue-3', 'queue-2']
        );

        $reserver->setPriorities([
            'queue-1' => 1,
            'queue-3' => 10,
            'queue-2' => 5,
        ]);

        $reserver->setMinPriority(3);
        $reserver->setMaxPriority(8);
        $reserver->beforework();

        $queues = $reserver->getQueues();
        $this->assertCount(1, $reserver->getQueues());
        $this->assertEquals('queue-2', (string) $queues[0]);
    }

    /** @test */
    public function shouldByMinPriorityQueuesBeforeWork()
    {
        $reserver = new PriorityReserver(
            $this->client->queues,
            ['queue-1', 'queue-3', 'queue-2']
        );

        $reserver->setPriorities([
            'queue-1' => 1,
            'queue-3' => 10,
            'queue-2' => 5,
        ]);

        $reserver->setMinPriority(3);
        $reserver->beforework();

        $queues = $reserver->getQueues();
        $this->assertCount(2, $reserver->getQueues());
        $this->assertEquals('queue-3', (string) $queues[0]);
        $this->assertEquals('queue-2', (string) $queues[1]);
    }

    /** @test */
    public function shouldByMaxPriorityQueuesBeforeWork()
    {
        $reserver = new PriorityReserver(
            $this->client->queues,
            ['queue-1', 'queue-3', 'queue-2']
        );

        $reserver->setPriorities([
            'queue-1' => 1,
            'queue-3' => 10,
            'queue-2' => 5,
        ]);

        $reserver->setMaxPriority(8);
        $reserver->beforework();

        $queues = $reserver->getQueues();
        $this->assertCount(2, $reserver->getQueues());
        $this->assertEquals('queue-2', (string) $queues[0]);
        $this->assertEquals('queue-1', (string) $queues[1]);
    }
}

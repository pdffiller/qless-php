<?php

namespace Qless\Tests\Jobs\Reservers;

use Psr\Log\NullLogger;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Jobs\Reservers\OrderedReserverTest
 *
 * @package Qless\Tests\Jobs\Reservers
 */
class OrderedReserverTest extends QlessTestCase
{
    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionForNoQueuesAndSpec(): void
    {
        $this->expectExceptionMessage("A queues list or a specification to reserve queues are required.");
        $this->expectException(InvalidArgumentException::class);
        new OrderedReserver($this->client->queues, []);
    }

    /** @test */
    public function shouldReserveJob(): void
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $_SERVER['performed'] = '';

        $class = new class {
            public function perform(BaseJob $job): void
            {
                $_SERVER['performed'] = "{$job->queue}:{$job->data[0]}";
            }
        };

        $queue1->put(get_class($class), ['foo']);
        $queue2->put(get_class($class), ['bar']);

        $reserver = new OrderedReserver($this->client->queues, ['queue-2', 'queue-1']);
        $job = $reserver->reserve();

        $this->assertIsJob($job);
        self::assertTrue($job->perform());
        self::assertEquals('queue-1:foo', $_SERVER['performed']);
    }

    /** @test */
    public function shouldGetQueues(): void
    {
        $reserver = new OrderedReserver($this->client->queues, ['queue-1', 'queue-2']);

        self::assertEquals(
            [new Queue('queue-1', $this->client), new Queue('queue-2', $this->client)],
            $reserver->getQueues()
        );
    }

    /** @test */
    public function shouldGetDescription(): void
    {
        $reserver = new OrderedReserver($this->client->queues, ['queue-1', 'queue-2']);

        self::assertEquals('queue-1, queue-2 (ordered)', $reserver->getDescription());
    }

    /** @test */
    public function shouldGetNullOnEmptyQueue(): void
    {
        $reserver = new OrderedReserver($this->client->queues, ['queue-1', 'queue-2']);

        self::assertNull($reserver->reserve());
    }

    /** @test */
    public function shouldSortQueues(): void
    {
        $reserver = new OrderedReserver($this->client->queues, ['queue-7', 'queue-20', 'queue-0']);

        self::assertEquals('queue-7, queue-20, queue-0 (ordered)', $reserver->getDescription());

        $reserver->beforeWork();
        self::assertEquals('queue-0, queue-7, queue-20 (ordered)', $reserver->getDescription());
    }

    /** @test */
    public function shouldReserveQueuesBySpec(): void
    {
        $spec = 'eu-(west|east)-\d+';
        $reserver = new OrderedReserver($this->client->queues, null, $spec);
        $reserver->setLogger(new NullLogger());

        $this->client->put('w1', 'foo', 'j1', 'klass', '{}', 0);
        self::assertNull($reserver->reserve());

        $this->client->put('w1', 'eu-west-2', 'j1', 'klass', '{}', 0);

        $job = $reserver->reserve();

        $this->assertIsJob($job);
        self::assertEquals('j1', $job->jid);

        $job->complete('eu-east-1');

        $job = $reserver->reserve();

        $this->assertIsJob($job);
        self::assertEquals('j1', $job->jid);
    }
}

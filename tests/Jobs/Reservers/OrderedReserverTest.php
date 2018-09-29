<?php

namespace Qless\Tests\Jobs\Reservers;

use Qless\Jobs\Job;
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
    /** @test */
    public function shouldReturnNulForNoQueues()
    {
        $reserver = new OrderedReserver([]);

        $this->assertEquals([], $reserver->getQueues());
        $this->assertNull($reserver->reserve());
    }

    /** @test */
    public function shouldReserveJob()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $_SERVER['performed'] = '';

        $class = new class {
            public function perform(Job $job): void
            {
                $_SERVER['performed'] = "{$job->queue}:{$job->data[0]}";
            }
        };

        $queue1->put(get_class($class), ['foo']);
        $queue2->put(get_class($class), ['bar']);

        $reserver = new OrderedReserver([$queue2, $queue1]);
        $job = $reserver->reserve();

        $this->assertIsJob($job);
        $this->assertTrue($job->perform());
        $this->assertEquals('queue-2:bar', $_SERVER['performed']);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @dataProvider queuesDataProvider
     *
     * @param string $expectedType
     * @param array  $queues
     */
    public function shouldThrowExpectedExceptionOnInvalidQueueList(array $queues, string $expectedType)
    {
        $this->expectExceptionMessage(
            sprintf(
                'The "%s" resever should be initialized using an array of "%s" instances, the "%s" given.',
                OrderedReserver::class,
                Queue::class,
                $expectedType
            )
        );

        new OrderedReserver($queues);
    }

    public function queuesDataProvider(): array
    {
        return [
            [[new \stdClass()], \stdClass::class],
            [[null           ], 'NULL'],
            [[[]             ], 'array'],
            [['test-queue-1' ], 'string'],
        ];
    }

    /** @test */
    public function shouldNormalConstructObjectWithQueuesStack()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $stack = [$queue1, $queue2];

        $reserver = new OrderedReserver($stack);

        $this->assertEquals($stack, $reserver->getQueues());
    }

    /** @test */
    public function shouldGetDescription()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $reserver = new OrderedReserver([$queue1, $queue2]);

        $this->assertEquals('queue-1, queue-2 (ordered)', $reserver->getDescription());
    }
}

<?php

namespace Qless\Tests\Jobs\Reservers;

use Qless\Jobs\Job;
use Qless\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Jobs\Reservers\RoundRobinReserver;

/**
 * Qless\Tests\Jobs\Reservers\RoundRobinReserverTest
 *
 * @package Qless\Tests\Jobs\Reservers
 */
class RoundRobinReserverTest extends QlessTestCase
{
    /** @test */
    public function shouldReturnNulForNoQueues()
    {
        $reserver = new RoundRobinReserver([]);

        $this->assertEquals([], $reserver->getQueues());
        $this->assertNull($reserver->reserve());
    }

    /** @test */
    public function shouldReserveJob()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);
        $queue3 = new Queue('queue-3', $this->client);

        $_SERVER['performed'] = '';

        $class = new class {
            public function perform(Job $job): void
            {
                $_SERVER['performed'] .= "{$job->data[0]}";
            }
        };

        $queue1->put(get_class($class), ['baz']);
        $queue2->put(get_class($class), ['bar-']);
        $queue3->put(get_class($class), ['foo-']);

        $reserver = new RoundRobinReserver([$queue3, $queue2, $queue1]);

        $reserver->reserve()->perform();
        $reserver->reserve()->perform();
        $reserver->reserve()->perform();

        $this->assertEquals('foo-bar-baz', $_SERVER['performed']);
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
                RoundRobinReserver::class,
                Queue::class,
                $expectedType
            )
        );

        new RoundRobinReserver($queues);
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

        $reserver = new RoundRobinReserver($stack);

        $this->assertEquals($stack, $reserver->getQueues());
    }

    /** @test */
    public function shouldGetDescription()
    {
        $queue1 = new Queue('queue-1', $this->client);
        $queue2 = new Queue('queue-2', $this->client);

        $reserver = new RoundRobinReserver([$queue1, $queue2]);

        $this->assertEquals('queue-1, queue-2 (round robin)', $reserver->getDescription());
    }
}

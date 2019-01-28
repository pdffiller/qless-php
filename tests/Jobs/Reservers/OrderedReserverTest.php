<?php

namespace Qless\Tests\Jobs\Reservers;

use Psr\Log\NullLogger;
use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\Options\DefaultOptions;
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
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage A queues list or a specification to reserve queues are required.
     */
    public function shouldThrowExceptionForNoQueuesAndSpec()
    {
        $reserverOptions = new DefaultOptions($this->client->queues);
        new OrderedReserver($reserverOptions);
    }

    /** @test */
    public function shouldReserveJob()
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

        $reserverOptions = new DefaultOptions($this->client->queues);
        $reserverOptions->setQueues(['queue-2', 'queue-1']);
        $reserver = new OrderedReserver($reserverOptions);

        $job = $reserver->reserve();

        $this->assertIsJob($job);
        $this->assertTrue($job->perform());
        $this->assertEquals('queue-1:foo', $_SERVER['performed']);
    }

    /** @test */
    public function shouldGetQueues()
    {
        $reserverOptions = new DefaultOptions($this->client->queues);
        $reserverOptions->setQueues(['queue-1', 'queue-2']);
        $reserver = new OrderedReserver($reserverOptions);

        $this->assertEquals(
            [new Queue('queue-1', $this->client), new Queue('queue-2', $this->client)],
            $reserver->getQueues()
        );
    }

    /** @test */
    public function shouldGetDescription()
    {
        $reserverOptions = new DefaultOptions($this->client->queues);
        $reserverOptions->setQueues(['queue-1', 'queue-2']);
        $reserver = new OrderedReserver($reserverOptions);

        $this->assertEquals('queue-1, queue-2 (ordered)', $reserver->getDescription());
    }

    /** @test */
    public function shouldGetNullOnEmptyQueue()
    {
        $reserverOptions = new DefaultOptions($this->client->queues);
        $reserverOptions->setQueues(['queue-1', 'queue-2']);
        $reserver = new OrderedReserver($reserverOptions);

        $this->assertNull($reserver->reserve());
    }

    /** @test */
    public function shouldSortQueues()
    {
        $reserverOptions = new DefaultOptions($this->client->queues);
        $reserverOptions->setQueues(['queue-7', 'queue-20', 'queue-0']);
        $reserver = new OrderedReserver($reserverOptions);

        $this->assertEquals('queue-7, queue-20, queue-0 (ordered)', $reserver->getDescription());

        $reserver->beforeWork();
        $this->assertEquals('queue-0, queue-7, queue-20 (ordered)', $reserver->getDescription());
    }

    /** @test */
    public function shouldReserveQueuesBySpec()
    {
        $spec = 'eu-(west|east)-\d+';
        $reserverOptions = new DefaultOptions($this->client->queues);
        $reserverOptions->setSpec($spec);
        $reserver = new OrderedReserver($reserverOptions);
        $reserver->setLogger(new NullLogger());

        $this->client->put('w1', 'foo', 'j1', 'klass', '{}', 0);
        $this->assertNull($reserver->reserve());

        $this->client->put('w1', 'eu-west-2', 'j1', 'klass', '{}', 0);

        $job = $reserver->reserve();

        $this->assertIsJob($job);
        $this->assertEquals('j1', $job->jid);

        $job->complete('eu-east-1');

        $job = $reserver->reserve();

        $this->assertIsJob($job);
        $this->assertEquals('j1', $job->jid);
    }
}

<?php

namespace Qless\Tests\Queues;

use Qless\Exceptions\UnsupportedMethodException;
use Qless\Queues\Queue;
use Qless\Tests\Support\LightClientTrait;

class JobCollectionLightTest extends JobCollectionTest
{
    use LightClientTrait;

    protected function populateQueue(Queue $queue): void
    {
        $queue->put('XX\\YY\\ZZ', ['foo' => 'bar'], 'job-1');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'bar2'], 'job-2');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'boo'], 'job-3');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'baz'], 'job-4', 300);
        $queue->pop();
    }

    public function testGetsRecurring(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testGetsRecurring();
    }

    public function testGetsDepends(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testGetsDepends();
    }

    public function testGetsRunning(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $running = $queue->jobs->running();

        self::assertEquals('job-3', $running['job-3']->jid, 'Running job is returned');
        self::assertCount(1, $running, 'Only Running job is returned');
    }

    public function testGetsWaiting(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $waiting = $queue->jobs->waiting();

        self::assertEquals('job-1', $waiting['job-1']->jid, 'Waiting job is returned');
        self::assertEquals('job-2', $waiting['job-2']->jid, 'Waiting job is returned');
        self::assertCount(2, $waiting, 'Only Waiting job is returned');
    }

    public function testGetsStalled(): void
    {
        $queue = $this->client->queues['foo'];
        $queue->setHeartbeat(1);

        $this->populateQueue($queue);
        $queue->pop();
        \sleep(2);

        $stalled = $queue->jobs->stalled();

        self::assertEquals('job-2', $stalled['job-2']->jid, 'Stalled job is returned');
        self::assertEquals('job-3', $stalled['job-3']->jid, 'Stalled job is returned');
        self::assertCount(2, $stalled, 'Only stalled jobs are returned');
    }
}

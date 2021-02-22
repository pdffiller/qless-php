<?php

namespace Qless\Tests\Queues;

use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Queues\JobCollectionTest
 *
 * @package Qless\Tests\Queues
 */
class JobCollectionTest extends QlessTestCase
{

    protected function populateQueue(Queue $queue): void
    {
        $queue->put('XX\\YY\\ZZ', ['foo' => 'bar'], 'job-1');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'bar2'], 'job-2');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'boo'], 'job-3');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'baz'], 'job-4', 300);
        $queue->put('XX\\YY\\ZZ', ['baz' => 'bar'], 'job-5', null, null, null, null, ['job-2']);
        $queue->recur('XX\\YY\\ZZ', ['baz2' => 'bar'], null, 300, 'job-6');
        $queue->pop();
    }


    public function testGetsRunning(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $running = $queue->jobs->running();

        self::assertEquals('job-1', $running['job-1']->jid, 'Running job is returned');
        self::assertCount(1, $running, 'Only Running job is returned');
    }

    public function testGetsWaiting(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $waiting = $queue->jobs->waiting();

        self::assertEquals('job-2', $waiting['job-2']->jid, 'Waiting job is returned');
        self::assertEquals('job-3', $waiting['job-3']->jid, 'Waiting job is returned');
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

        self::assertEquals('job-1', $stalled['job-1']->jid, 'Stalled job 1 is returned');
        self::assertEquals('job-2', $stalled['job-2']->jid, 'Stalled job 1 is returned');
        self::assertCount(2, $stalled, 'Only stalled jobs are returned');
    }

    public function testGetsScheduled(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $scheduled = $queue->jobs->scheduled();

        self::assertEquals('job-4', $scheduled['job-4']->jid, 'Scheduled job is returned');
        self::assertCount(1, $scheduled, 'Only Scheduled job is returned');
    }

    public function testGetsRecurring(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $recurring = $queue->jobs->recurring();

        self::assertEquals('job-6', $recurring['job-6']->jid, 'Recurring job is returned');
        self::assertCount(1, $recurring, 'Only Recurring job is returned');
    }

    public function testGetsDepends(): void
    {
        $queue = $this->client->queues['foo'];

        $this->populateQueue($queue);

        $depends = $queue->jobs->depends();

        self::assertEquals('job-5', $depends['job-5']->jid, 'Dependent job is returned');
        self::assertEquals(['job-2'], $depends['job-5']->dependencies, 'Dependent job has dependencies');
        self::assertCount(1, $depends, 'Only Scheduled job is returned');
    }
}

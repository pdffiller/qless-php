<?php

namespace Qless\Tests\Queues;

use Qless\Exceptions\QlessException;
use Qless\Tests\QlessTestCase;
use Qless\Queues\Queue;

/**
 * Qless\Tests\Queues\QueueTest
 *
 * @package Qless\Tests\Queues
 */
class QueueTest extends QlessTestCase
{
    /**
     * @test
     */
    public function shouldPutAndPopAJob(): void
    {
        $queue = new Queue('test-queue', $this->client);

        $queue->put('Xxx\Yyy', [], 'jid');

        $job = $queue->pop();

        $this->assertIsJob($job);
        self::assertEquals('jid', $job->jid);
    }

    /**
     * @test
     */
    public function shouldGenerateAJobIdIfNotProvided(): void
    {
        $queue = new Queue('test-queue', $this->client);
        self::assertRegExp('/^[[:xdigit:]]{32}$/', $queue->put('Xxx\Yyy', []));
        self::assertRegExp('/^[[:xdigit:]]{32}$/', $queue->recur('Xxx\Yyy', []));
    }

    /**
     * @test
     */
    public function shouldGetNullWithoutAnyJobInTheQueue(): void
    {
        self::assertNull((new Queue('test-queue', $this->client))->pop());
    }

    /**
     * @test
     */
    public function shouldGetTheQueueLength(): void
    {
        $queue = new Queue('test-queue', $this->client);

        array_map(function (int $id) use ($queue): void {
            $queue->put('SampleClass', [], "jid-{$id}");
        }, range(1, 10));

        self::assertEquals(10, $queue->length());
    }

    /**
     * @test
     */
    public function shouldPopMultipleJobs(): void
    {
        $queue = new Queue('test-queue-2', $this->client);

        foreach (range(1, 10) as $jid) {
            $queue->put('Xxx\Yyy', [], "jid-{$jid}");
        }

        self::assertCount(10, $queue->pop(null, 10));
    }

    /**
     * @test
     */
    public function shouldPutAndPopInTheSameOrder(): void
    {
        $queue = new Queue('test-queue', $this->client);

        $putJids = array_map(function (int $id) use ($queue): string {
            return $queue->put('SampleHandler', [], "jid-{$id}");
        }, range(1, 10));


        $popJids = array_map(function () use ($queue): string {
            return $queue->pop()->jid;
        }, $putJids);

        self::assertIsArray($putJids);
        self::assertIsArray($popJids);

        self::assertCount(10, $putJids);
        self::assertCount(10, $popJids);

        self::assertEquals($putJids, $popJids);
    }

    /**
     * @test
     */
    public function shouldPutJobWithPriority(): void
    {
        $queue = new Queue('test-queue', $this->client);

        $jid = $queue->put('SampleHandler', [], null, null, null, -10);
        $job = $queue->pop();

        self::assertEquals($jid, $job->jid);
        self::assertEquals(-10, $job->priority);

        $job->complete();

        $jid = $queue->put('SampleHandler', []);
        $job = $queue->pop();

        self::assertEquals($jid, $job->jid);
        self::assertEquals(0, $job->priority);
    }

    /**
     * @test
     */
    public function shouldPopJobsWithHigherPriorityFirst(): void
    {
        $queue = new Queue('test-queue', $this->client);

        $putJids = array_map(function (int $id) {
            return "jid-{$id}";
        }, range(1, 10));

        shuffle($putJids);
        foreach ($putJids as $k => $jid) {
            $queue->put('SampleHandler', [], $jid, null, null, $k);
        }

        $popJids = array_map(function () use ($queue) {
            return $queue->pop()->jid;
        }, $putJids);

        self::assertIsArray($putJids);
        self::assertIsArray($popJids);

        self::assertCount(10, $putJids);
        self::assertCount(10, $popJids);

        self::assertEquals(array_reverse($putJids), $popJids);
    }

    /**
     * @test
     */
    public function shouldScheduleJob(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->put('MyJobClass', ['foo' => 'bar'], null, 1);

        self::assertNull($queue->pop());

        sleep(1);
        $this->assertIsJob($queue->pop());
    }

    public function testRunningJobIsReplaced(): void
    {
        $queue = new Queue('test-queue', $this->client);

        self::assertEquals('jid-1', $queue->put('Xxx\Yyy', [], "jid-1"));

        $queue->pop();

        self::assertEquals(
            'jid-1',
            $queue->put('Xxx\Yyy', [], "jid-1")
        );
    }

    public function testPausedQueueDoesNotReturnJobs(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->pause();
        $queue->put('Xxx\Yyy', []);

        self::assertNull($queue->pop());
    }

    public function testQueueIsNotPausedByDefault(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $val = $queue->isPaused();
        self::assertFalse($val);
    }

    public function testQueueCorrectlyReportsIsPaused(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->pause();
        $val = $queue->isPaused();
        self::assertTrue($val);
    }

    public function testPausedQueueThatIsResumedDoesReturnJobs(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->pause();
        $queue->put('Xxx\Yyy', ["performMethod" => 'myPerformMethod', "payload" => "otherData"]);
        $queue->resume();

        $this->assertIsJob($queue->pop());
    }

    public function testHighPriorityJobPoppedBeforeLowerPriorityJobs(): void
    {
        $queue = new Queue('test-queue', $this->client);

        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $queue->put('Xxx\Yyy', $data, "jid-1");
        $queue->put('Xxx\Yyy', $data, "jid-2");
        $queue->put('Xxx\Yyy', $data, "jid-high", 0, 0, 1);
        $queue->put('Xxx\Yyy', $data, "jid-3");

        self::assertEquals('jid-high', $queue->pop()->jid);
    }

    public function testItUsesGlobalHeartbeatValueWhenNotSet(): void
    {
        $this->client->config->set('heartbeat', 10);
        $queue = new Queue('test-queue', $this->client);

        self::assertSame(10, $queue->heartbeat);
    }

    public function testItUsesOwnHeartbeatValue(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->heartbeat = 55;

        self::assertSame(55, $queue->heartbeat);
    }

    public function testItCanUnsetHeartbeatValueForQueue(): void
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->heartbeat = 10;
        self::assertSame(10, $queue->heartbeat);
        unset($queue->heartbeat);
        self::assertSame(60, $queue->heartbeat);
    }

    public function testItCanForgetEmptyQueue()
    {
        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Xxx\Yyy', $data, "jid-1");
        self::assertTrue(isset($this->client->queues['test-queue']));
        $queue->cancel("jid-1");
        $queue->forget();
        self::assertFalse(isset($this->client->queues['test-queue']));
    }

    public function testItCanForceForgetNonEmptyQueue()
    {
        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Xxx\Yyy', $data, "jid-1");
        self::assertTrue(isset($this->client->queues['test-queue']));
        $queue->forget(true);
        self::assertFalse(isset($this->client->queues['test-queue']));
    }

    public function testItCannotForgetNonEmptyQueue()
    {
        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Xxx\Yyy', $data, "jid-1");
        self::assertTrue(isset($this->client->queues['test-queue']));
        $this->expectException(QlessException::class);
        $this->expectExceptionMessage('Queue is not empty');
        try {
            $queue->forget();
        } finally {
            self::assertTrue(isset($this->client->queues['test-queue']));
        }
    }
}

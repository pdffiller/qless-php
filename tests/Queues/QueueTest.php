<?php

namespace Qless\Tests\Queues;

use Qless\Tests\QlessTestCase;
use Qless\Queues\Queue;

/**
 * Qless\Tests\Queues\QueueTest
 *
 * @package Qless\Tests\Queues
 */
class QueueTest extends QlessTestCase
{
    /** @test */
    public function shouldPutAndPopAJob()
    {
        $queue = new Queue('test-queue', $this->client);

        $queue->put('Xxx\Yyy', [], 'jid');

        $job = $queue->pop();

        $this->assertIsJob($job);
        $this->assertEquals('jid', $job->jid);
    }

    /** @test */
    public function shouldGenerateAJobIdIfNotProvided()
    {
        $queue = new Queue('test-queue', $this->client);

        $this->assertRegExp(
            '/^[[:xdigit:]]{8}-([[:xdigit:]]{4}-){3}[[:xdigit:]]{12}/',
            $queue->put('Xxx\Yyy', [])
        );
    }

    /** @test */
    public function shouldGetNullWithoutAnyJobInTheQueue()
    {
        $this->assertNull((new Queue('test-queue', $this->client))->pop());
    }

    /** @test */
    public function shouldGetTheQueueLength()
    {
        $queue = new Queue('test-queue', $this->client);

        array_map(function (int $id) use ($queue): void {
            $queue->put('SampleClass', [], "jid-{$id}");
        }, range(1, 10));

        $this->assertEquals(10, $queue->length());
    }

    /** @test */
    public function shouldPopMultipleJobs()
    {
        $queue = new Queue('test-queue-2', $this->client);

        foreach (range(1, 10) as $jid) {
            $queue->put('Xxx\Yyy', [], "jid-{$jid}");
        }

        $this->assertCount(10, $queue->pop(null, 10));
    }

    /** @test */
    public function shouldPutAndPopInTheSameOrder()
    {
        $queue = new Queue('test-queue', $this->client);

        $putJids = array_map(function (int $id) use ($queue): string {
            return $queue->put('SampleHandler', [], "jid-{$id}");
        }, range(1, 10));


        $popJids = array_map(function () use ($queue): string {
            return $queue->pop()->jid;
        }, $putJids);

        $this->assertTrue(is_array($putJids));
        $this->assertTrue(is_array($popJids));

        $this->assertCount(10, $putJids);
        $this->assertCount(10, $popJids);

        $this->assertEquals($putJids, $popJids);
    }

    /** @test */
    public function shouldPutJobWithPriority()
    {
        $queue = new Queue('test-queue', $this->client);

        $jid = $queue->put('SampleHandler', [], null, null, null, -10);
        $job = $queue->pop();

        $this->assertEquals($jid, $job->jid);
        $this->assertEquals(-10, $job->priority);

        $job->complete();

        $jid = $queue->put('SampleHandler', []);
        $job = $queue->pop();

        $this->assertEquals($jid, $job->jid);
        $this->assertEquals(0, $job->priority);
    }

    /** @test */
    public function shouldPopJobsWithHigherPriorityFirst()
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

        $this->assertTrue(is_array($putJids));
        $this->assertTrue(is_array($popJids));

        $this->assertCount(10, $putJids);
        $this->assertCount(10, $popJids);

        $this->assertEquals(array_reverse($putJids), $popJids);
    }

    public function testRunningJobIsReplaced()
    {
        $queue = new Queue('test-queue', $this->client);

        $this->assertEquals('jid-1', $queue->put('Xxx\Yyy', [], "jid-1"));

        $queue->pop();

        $this->assertEquals(
            'jid-1',
            $queue->put('Xxx\Yyy', [], "jid-1")
        );
    }

    public function testPausedQueueDoesNotReturnJobs()
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->pause();
        $queue->put('Xxx\Yyy', []);

        $this->assertNull($queue->pop());
    }

    public function testQueueIsNotPausedByDefault()
    {
        $queue = new Queue('test-queue', $this->client);
        $val = $queue->isPaused();
        $this->assertFalse($val);
    }

    public function testQueueCorrectlyReportsIsPaused()
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->pause();
        $val = $queue->isPaused();
        $this->assertTrue($val);
    }

    public function testPausedQueueThatIsResumedDoesReturnJobs()
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->pause();
        $queue->put('Xxx\Yyy', ["performMethod" => 'myPerformMethod', "payload" => "otherData"]);
        $queue->resume();

        $this->assertIsJob($queue->pop());
    }

    public function testHighPriorityJobPoppedBeforeLowerPriorityJobs()
    {
        $queue = new Queue('test-queue', $this->client);

        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $queue->put('Xxx\Yyy', $data, "jid-1");
        $queue->put('Xxx\Yyy', $data, "jid-2");
        $queue->put('Xxx\Yyy', $data, "jid-high", 0, 0, 1);
        $queue->put('Xxx\Yyy', $data, "jid-3");

        $this->assertEquals('jid-high', $queue->pop()->jid);
    }

    public function testItUsesGlobalHeartbeatValueWhenNotSet()
    {
        $this->client->config->set('heartbeat', 10);
        $queue = new Queue('test-queue', $this->client);

        $this->assertSame(10, $queue->heartbeat);
    }

    public function testItUsesOwnHeartbeatValue()
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->heartbeat = 55;

        $this->assertSame(55, $queue->heartbeat);
    }

    public function testItCanUnsetHeartbeatValueForQueue()
    {
        $queue = new Queue('test-queue', $this->client);
        $queue->heartbeat = 10;
        $this->assertSame(10, $queue->heartbeat);
        unset($queue->heartbeat);
        $this->assertSame(60, $queue->heartbeat);
    }
}

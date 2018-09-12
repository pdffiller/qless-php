<?php

namespace Qless\Tests;

use Qless\Queue;

/**
 * Qless\Tests\QueueTest
 *
 * @package Qless\Tests
 */
class QueueTest extends QlessTestCase
{
    public function testPutAndPop()
    {
        $queue = new Queue('test-queue', $this->client);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $queue->put('Xxx\Yyy', $testData, "jid");

        $job = $queue->pop();

        $this->assertIsJob($job);
        $this->assertEquals('jid', $job->jid);
    }

    public function testPutWithFalseJobIDGeneratesUUID()
    {
        $queue = new Queue('test-queue', $this->client);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $res = $queue->put('Xxx\Yyy', $testData, null);
        $this->assertRegExp('/^[[:xdigit:]]{8}-([[:xdigit:]]{4}-){3}[[:xdigit:]]{12}/', $res);
    }

    /** @test */
    public function shouldGetNullWithoutAnyJobInTheQueue()
    {
        $this->assertNull((new Queue('test-queue', $this->client))->pop());
    }

    public function testQueueLength()
    {
        $queue = new Queue('test-queue', $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        foreach (range(1, 10) as $i) {
            $queue->put('Xxx\Yyy', $testData, "jid-" . $i);
        }
        $len = $queue->length();
        $this->assertEquals(10, $len);
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

    public function testCorrectOrderOfPushingAndPoppingJobs()
    {
        $queue = new Queue('test-queue', $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function ($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $jid) {
            $queue->put('Xxx\Yyy', $testData, $jid);
        }

        $results = array_map(function () use ($queue) {
            return $queue->pop()->jid;
        }, $jids);

        $this->assertEquals($jids, $results);
    }

    public function testHigherPriorityJobsArePoppedSooner()
    {
        $queue = new Queue('test-queue', $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function ($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $k => $jid) {
            $queue->put('Xxx\Yyy', $testData, $jid, 0, 5, true, $k);
        }

        $results = array_map(function () use ($queue) {
            return $queue->pop()->jid;
        }, $jids);

        $this->assertEquals(array_reverse($jids), $results);
    }

    public function testRunningJobIsReplaced()
    {
        $queue = new Queue('test-queue', $this->client);

        $this->assertEquals('jid-1', $queue->put('Xxx\Yyy', [], "jid-1"));

        $queue->pop();

        $this->assertEquals(
            'jid-1',
            $queue->put('Xxx\Yyy', [], "jid-1", 0, 5, true)
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
        $queue->put('Xxx\Yyy', $data, "jid-high", 0, 0, true, 1);
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

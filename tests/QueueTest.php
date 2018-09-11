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
        $queue = new Queue("testQueue", $this->client);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $res = $queue->put("Sample\\TestWorkerImpl", $testData, "jid");
        $jobs = $queue->pop("worker");
        $this->assertNotEmpty($jobs);
        $this->assertEquals('jid', $jobs[0]->getId());
    }

    public function testPutWithFalseJobIDGeneratesUUID()
    {
        $queue = new Queue("testQueue", $this->client);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $res = $queue->put("Sample\\TestWorkerImpl", $testData, null);
        $this->assertRegExp('/^[[:xdigit:]]{8}-([[:xdigit:]]{4}-){3}[[:xdigit:]]{12}/', $res);
    }

    public function testPopWithNoJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $jobs = $queue->pop("worker");
        $this->assertEmpty($jobs);
    }

    public function testQueueLength()
    {
        $queue = new Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        foreach (range(1, 10) as $i) {
            $queue->put("Sample\\TestWorkerImpl", $testData, "jid-" . $i);
        }
        $len = $queue->length();
        $this->assertEquals(10, $len);
    }

    public function testPoppingMultipleJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function ($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $jid) {
            $queue->put("Sample\\TestWorkerImpl", $testData, $jid);
        }

        $results = $queue->pop('worker', 10);

        $numJobs = count($results);
        $this->assertEquals(10, $numJobs);
    }

    public function testCorrectOrderOfPushingAndPoppingJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function ($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $jid) {
            $queue->put("Sample\\TestWorkerImpl", $testData, $jid);
        }

        $results = array_map(function () use ($queue) {
            return $queue->pop('worker')[0]->getId();
        }, $jids);

        $this->assertEquals($jids, $results);
    }

    public function testHigherPriorityJobsArePoppedSooner()
    {
        $queue = new Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function ($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $k => $jid) {
            $queue->put("Sample\\TestWorkerImpl", $testData, $jid, 0, 5, true, $k);
        }

        $results = array_map(function () use ($queue) {
            return $queue->pop('worker')[0]->getId();
        }, $jids);

        $this->assertEquals(array_reverse($jids), $results);
    }

    public function testRunningJobIsReplaced()
    {
        $queue = new Queue("testQueue", $this->client);
        $res = $queue->put("Sample\\TestWorkerImpl", [], "jid-1");
        $this->assertEquals('jid-1', $res);
        $jobs = $queue->pop("worker");
        $res = $queue->put("Sample\\TestWorkerImpl", [], "jid-1", 0, 5, true);
        $this->assertEquals('jid-1', $res);
    }

    public function testPausedQueueDoesNotReturnJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $queue->pause();
        $queue->put("Sample\\TestWorkerImpl", ["performMethod" => 'myPerformMethod', "payload" => "otherData"]);
        $jobs = $queue->pop("worker");
        $this->assertEquals([], $jobs);
    }

    public function testQueueIsNotPausedByDefault()
    {
        $queue = new Queue("testQueue", $this->client);
        $val = $queue->isPaused();
        $this->assertFalse($val);
    }

    public function testQueueCorrectlyReportsIsPaused()
    {
        $queue = new Queue("testQueue", $this->client);
        $queue->pause();
        $val = $queue->isPaused();
        $this->assertTrue($val);
    }

    public function testPausedQueueThatIsResumedDoesReturnJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $queue->pause();
        $queue->put("Sample\\TestWorkerImpl", ["performMethod" => 'myPerformMethod', "payload" => "otherData"]);
        $queue->resume();
        $jobs = $queue->pop("worker");
        $this->assertNotEmpty($jobs);
    }

    public function testHighPriorityJobPoppedBeforeLowerPriorityJobs()
    {
        $queue = new Queue("testQueue", $this->client);

        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $queue->put("Sample\\TestWorkerImpl", $data, "jid-1");
        $queue->put("Sample\\TestWorkerImpl", $data, "jid-2");
        $queue->put("Sample\\TestWorkerImpl", $data, "jid-high", 0, 0, true, 1);
        $queue->put("Sample\\TestWorkerImpl", $data, "jid-3");

        $job = $queue->pop('worker')[0];
        $this->assertEquals('jid-high', $job->getId());
    }

    public function testItUsesGlobalHeartbeatValueWhenNotSet()
    {
        $this->client->config->set('heartbeat', 10);
        $queue = new Queue("testQueue", $this->client);

        $this->assertSame(10, $queue->heartbeat);
    }

    public function testItUsesOwnHeartbeatValue()
    {
        $queue = new Queue("testQueue", $this->client);
        $queue->heartbeat = 55;

        $this->assertSame(55, $queue->heartbeat);
    }

    public function testItCanUnsetHeartbeatValueForQueue()
    {
        $queue = new Queue("testQueue", $this->client);
        $queue->heartbeat = 10;
        $this->assertSame(10, $queue->heartbeat);
        unset($queue->heartbeat);
        $this->assertSame(60, $queue->heartbeat);
    }
}

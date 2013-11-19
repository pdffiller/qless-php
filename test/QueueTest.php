<?php


require_once __DIR__ . '/QlessTest.php';

class QueueTest extends QlessTest {

    public function testPutAndPop(){
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $res = $queue->put("Sample\\TestWorkerImpl", "jid", $testData);
        $job = $queue->pop("worker");
        $this->assertNotNull($job);
        $this->assertEquals('jid', $job->getId());
    }

    public function testPopWithNoJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $job = $queue->pop("worker");
        $this->assertNull($job);
    }

    public function testQueueLength() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        foreach (range(1, 10) as $i) {
            $queue->put("Sample\\TestWorkerImpl", "jid-" . $i, $testData);
        }
        $len = $queue->length();
        $this->assertEquals(10, $len);
    }

    public function testCorrectOrderOfPushingAndPoppingJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $jid) {
            $queue->put("Sample\\TestWorkerImpl", $jid, $testData);
        }

        $results = array_map(function () use ($queue) {
            return $queue->pop('worker')->getId();
        }, $jids);

        $this->assertEquals($jids, $results);
    }

    public function testPoppingMultipleJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $jid) {
            $queue->put("Sample\\TestWorkerImpl", $jid, $testData);
        }

        $results = [];
        $resultJobs = $queue->popMultiple('worker', 10);
        foreach($resultJobs as $job) {
            $results[] = $job->getId();
        }
        $this->assertEquals($jids, $results);
    }

    public function testHigherPriorityJobsArePoppedSooner() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $jids = array_map(function($i) {
            return "jid-$i";
        }, range(1, 10));

        foreach ($jids as $k => $jid) {
            $queue->put("Sample\\TestWorkerImpl", $jid, $testData, 0, 5, true, $k);
        }

        $results = array_map(function () use ($queue) {
            return $queue->pop('worker')->getId();
        }, $jids);

        $this->assertEquals(array_reverse($jids), $results);
    }

    public function testRunningJobIsNotReplaced() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $res = $queue->put("Sample\\TestWorkerImpl", "jid-1", []);
        $this->assertEquals('jid-1', $res);
        $job = $queue->pop("worker");
        $res = $queue->put("Sample\\TestWorkerImpl", "jid-1", [], 0, 5, false);
        $this->assertGreaterThan(0, $res);
    }

    public function testRunningJobIsReplaced() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $res = $queue->put("Sample\\TestWorkerImpl", "jid-1", []);
        $this->assertEquals('jid-1', $res);
        $job = $queue->pop("worker");
        $res = $queue->put("Sample\\TestWorkerImpl", "jid-1", [], 0, 5, true);
        $this->assertEquals('jid-1', $res);
    }

    public function testPausedQueueDoesNotReturnJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $queue->pause();
        $queue->put("Sample\\TestWorkerImpl", "jid", ["performMethod"=>'myPerformMethod',"payload"=>"otherData"]);
        $job = $queue->pop("worker");
        $this->assertNull($job);
    }

    public function testQueueIsNotPausedByDefault() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $val = $queue->isPaused();
        $this->assertFalse($val);
    }

    public function testQueueCorrectlyReportsIsPaused() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $queue->pause();
        $val = $queue->isPaused();
        $this->assertTrue($val);
    }

    public function testPausedQueueThatIsResumedDoesReturnJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $queue->pause();
        $queue->put("Sample\\TestWorkerImpl", "jid", ["performMethod"=>'myPerformMethod',"payload"=>"otherData"]);
        $queue->resume();
        $job = $queue->pop("worker");
        $this->assertNotNull($job);
    }

    public function testHighPriorityJobPoppedBeforeLowerPriorityJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $queue->put("Sample\\TestWorkerImpl", "jid-1", ["performMethod"=>'myPerformMethod',"payload"=>"otherData"]);
        $queue->put("Sample\\TestWorkerImpl", "jid-2", ["performMethod"=>'myPerformMethod',"payload"=>"otherData"]);
        $queue->put("Sample\\TestWorkerImpl", "jid-high", ["performMethod"=>'myPerformMethod',"payload"=>"otherData"], 0, 0, true, 1);
        $queue->put("Sample\\TestWorkerImpl", "jid-3", ["performMethod"=>'myPerformMethod',"payload"=>"otherData"]);

        $job = $queue->pop('worker');
        $this->assertEquals('jid-high', $job->getId());
    }
}
 
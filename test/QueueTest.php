<?php


require_once __DIR__ . '/QlessTest.php';

class QueueTest extends QlessTest {

    public function testPutAndPop(){
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jid", $testData);
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


}
 
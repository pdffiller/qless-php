<?php

require_once __DIR__ . '/QlessTest.php';

class JobTest extends QlessTest
{
    /**
     * @expectedException \Qless\JobLostException
     */
    public function testHeartbeatForInvalidJobThrows() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jobTestDEF", $testData);

        $job1 = $queue->pop("worker-1");
        $queue->pop("worker-2");
        $job1->heartbeat();
    }

    public function testCompleteJob() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jobTestDEF", $testData);

        $job1 = $queue->pop("worker-1");
        $res = $job1->complete();
        $this->assertEquals('complete', $res);
    }

    public function testFailJobCannotBePopped() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jid", $testData);

        $job1 = $queue->pop("worker-1");
        $res = $job1->fail('account', 'failed to connect');
        $this->assertEquals('jid', $res);

        $job1 = $queue->pop("worker-1");
        $this->assertNull($job1);
    }

    public function testRetryDoesReturnJobAndDefaultsToFiveRetries() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jid", $testData);

        $job1 = $queue->pop("worker-1");
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(4, $remaining);

        $job1 = $queue->pop("worker-1");
        $this->assertEquals('jid', $job1->getId());
    }

    public function testRetryDoesRespectRetryParameterWithOneRetry() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jid", $testData, 0, 1);

        $job1 = $queue->pop("worker-1");
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(0, $remaining);

        $job1 = $queue->pop("worker-1");
        $this->assertEquals('jid', $job1->getId());
    }

    public function testRetryDoesReturnNegativeWhenNoMoreAvailable() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jid", $testData, 0, 0);

        $job1 = $queue->pop("worker-1");
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(-1, $remaining);
    }

    public function testRetryTransitionsToFailedWhenExhaustedRetries() {
        $queue = new Qless\Queue("testQueue", $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jid", $testData, 0, 0);

        $job1 = $queue->pop("worker-1");
        $job1->retry('account', 'failed to connect');

        $job1 = $queue->pop("worker-1");
        $this->assertNull($job1);

    }
}
 
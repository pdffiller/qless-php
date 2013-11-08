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
}
 
<?php


require_once __DIR__ . '/QlessTest.php';

class QueueTest extends QlessTest {

    public function testPutAndPop(){

        $queue = new Qless\Queue("testQueue", $this->client);

        //$queue->runDirect();

        //$queue->stats();
//        $testData = ["test1"=>"testdata1", "test2"=>"testdata2"];
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jobTestDEF", $testData);
        $len = $queue->length();
        $job = $queue->pop("worker");

 //       $result = $queue->pop();
    }

    public function testHeartbeatForInvalidJob() {
        $queue = new Qless\Queue("testQueue", $this->client);


        $val = $this->client->config->get('heartbeat');
        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $queue->put("Sample\\TestWorkerImpl", "jobTestDEF", $testData);
        $job1 = $queue->pop("worker-1");
        $job2 = $queue->pop("worker-2");
        $res = $job1->heartbeat();
    }


}
 
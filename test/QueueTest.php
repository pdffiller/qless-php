<?php
/**
 * Created by PhpStorm.
 * User: paul
 * Date: 10/31/13
 * Time: 4:50 PM
 */

require_once '../lib/Qless/Client.php';
require_once '../lib/Qless/Queue.php';

class QueueTest extends \PHPUnit_Framework_TestCase {

    public function testPutAndPop(){

        $client = new Qless\Client('localhost',6380);
        $queue = new Qless\Queue("testQueue",$client);

        //$queue->runDirect();

        //$queue->stats();
//        $testData = ["test1"=>"testdata1", "test2"=>"testdata2"];
        $testData = ["performMethod"=>'myPerformMethod',"payload"=>"otherData"];
        $queue->put("workerB", "Sample\\TestWorkerImpl", "jobTestDEF", $testData);
        //$job = $queue->pop("workerA");

 //       $result = $queue->pop();
    }
}
 
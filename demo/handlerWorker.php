<?php

require_once '../lib/Qless/Worker.php';
require_once '../lib/Qless/Queue.php';
require_once '../lib/Qless/Client.php';
require_once 'TestWorkerImpl.php';

class JobHandler {
    public function perform(Qless\Job $job){
        echo "Here in JobHandler perform";
        $instance = $job->getInstance();
        $data = $job->getData();
        $performMethod = $data['performMethod'];
        $instance->$performMethod($job);
    }
}

$queues = ['testQueue1','testQueue2'];
$client = new Qless\Client('localhost',6380);
$worker = new Qless\Worker("WorkerTest_1", $queues, $client, 5);
$worker->registerJobPerformHandler("JobHandler");

$worker->run();

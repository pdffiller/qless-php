<?php

use Qless\Client;
use Qless\Job;
use Qless\Worker;

require_once '../vendor/autoload.php';
require_once './TestWorkerImpl.php';
require_once './bootstrap.php';

class JobHandler
{
    public function perform(Job $job)
    {
        echo "Here in JobHandler perform";

        $instance = $job->getInstance();
        $data = $job->getData();
        $performMethod = $data['performMethod'];
        $instance->$performMethod($job);
    }
}

$queues = ['testQueue1', 'testQueue2'];
$client = new Client(REDIS_HOST, REDIS_PORT);
$worker = new Worker("WorkerTest_1", $queues, $client, 5);

$worker->registerJobPerformHandler("JobHandler");

$worker->run();

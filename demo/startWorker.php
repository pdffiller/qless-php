<?php

include '../lib/Qless/Worker.php';
include '../lib/Qless/Queue.php';
include '../lib/Qless/Client.php';
require 'TestWorkerImpl.php';

$queues = ['testQueue1','testQueue2'];
$client = new Qless\Client('localhost',6380);
$worker = new Qless\Worker($queues,$client,5);

$worker->run();

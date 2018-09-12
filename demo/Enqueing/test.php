<?php

use Qless\Client;
use Qless\Queue;
use Qless\Demo\Enqueing\MyJobClass;

require_once __DIR__ . '/../../tests/bootstrap.php';

// Connect to localhost
$client = new Client(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);

// This references a new or existing queue 'testing'
$queue = new Queue('testing', $client);

// Let's add a job, with some data. Returns Job ID
$jid = $queue->put(MyJobClass::class, ['hello' => 'howdy']);
// $jid here is "696c752a-7060-49cd-b227-a9fcfe9f681b"

// Now we can ask for a job
$job = $queue->pop();
// $job here is an array of the Qless\Job instances

// And we can do the work associated with it!
$job->perform();
// Perform 316eb06a-30d2-4d66-ad0d-33361306a7a1 job

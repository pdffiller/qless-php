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

// find an existing job by it's jid
$job = $client->jobs[$jid];

// query it to find out details about it:
$job->jid;          // the job id
$job->klass;        // the class of the job
$job->queue;        // the queue the job is in
$job->data;         // the data for the job
$job->history;      // the history of what has happened to the job so far
$job->dependencies; // the jids of other jobs that must complete before this one
$job->dependents;   // the jids of other jobs that depend on this one
$job->priority;     // the priority of this job
$job->worker;       // the internal worker name (usually consumer identifier)
$job->tags;         // array of tags for this job
$job->expires;      // when you must either check in with a heartbeat or turn it in as completed
$job->remaining;    // the number of retries remaining for this job
$job->retries;      // the number of retries originally requested

// there is a way to get seconds remaining before this job will timeout:
$job->ttl();

// you can also change the job in various ways:
$job->requeue('some_other_queue'); // move it to a new queue
$job->cancel();                    // cancel the job
$job->tag('foo');                  // add a tag
$job->untag('foo');                // remove a tag

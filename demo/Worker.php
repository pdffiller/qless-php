<?php

namespace Qless\Demo;

use Qless\Job;

/**
 * Qless\Demo\Worker
 *
 * @package Qless\Demo
 */
class Worker
{
    public function myPerformMethod(Job $job)
    {
        fprintf(STDOUT, "Here in %s\n", __METHOD__);

        $job->complete();

        // retry
        // heartbeat

        // fail
        // complete - on success, can throw in some cases, need to handle responses.

        // jobId = instance_id:job_name:user_id:additional if always unique
    }

    public function myThrowMethod(Job $job)
    {
        fprintf(STDERR, "Here in %s\n", __METHOD__);
        sleep(15);

        throw new \Exception('Sample job exception message.');
    }

    public function exitMethod(Job $job)
    {
        fprintf(STDERR, "Here in %s\n", __METHOD__);
        sleep(5);

        exit(1);
    }
}

<?php

namespace Qless\Tests\Stubs;

use Qless\Job;

/**
 * Qless\Tests\Stubs\WorkerStub2
 *
 * @package Qless\Demo
 */
class WorkerStub2
{
    public function myPerformMethod(Job $job): void
    {
        $job->complete();

        // retry
        // heartbeat

        // fail
        // complete - on success, can throw in some cases, need to handle responses.

        // jobId = instance_id:job_name:user_id:additional if always unique
    }

    /**
     * Default perform method.
     *
     * @param  Job $job
     * @return void
     */
    public function perform(Job $job): void
    {
        $job->complete();
    }

    public function myThrowMethod(Job $job): void
    {
        sleep(2);

        throw new \Exception('Sample job exception message.');
    }

    public function exitMethod(Job $job): void
    {
        sleep(1);

        exit(1);
    }
}

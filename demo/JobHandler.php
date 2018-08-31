<?php

namespace Qless\Demo;

use Qless\Job;
use Qless\Job\JobHandlerInterface;

/**
 * Qless\Demo\JobHandler
 *
 * @package Qless\Demo
 */
class JobHandler implements JobHandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Job $job
     * @return void
     *
     * @throws \Exception
     */
    public function perform(Job $job)
    {
        fprintf(STDOUT, "Here in %s perform\n", __CLASS__);

        $instance = $job->getInstance();
        $data = $job->getData();
        $performMethod = $data['performMethod'];

        $instance->$performMethod($job);
    }
}

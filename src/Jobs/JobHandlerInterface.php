<?php

namespace Qless\Jobs;

use Qless\Job;

/**
 * Qless\Jobs\JobHandlerInterface
 *
 * @package Qless\Job
 */
interface JobHandlerInterface
{
    /**
     * The Job perform handler.
     *
     * @param Job $job
     * @return void
     */
    public function perform(Job $job);
}

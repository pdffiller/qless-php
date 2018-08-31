<?php

namespace Qless\Job;

use Qless\Job;

/**
 * Qless\Job\JobHandlerInterface
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

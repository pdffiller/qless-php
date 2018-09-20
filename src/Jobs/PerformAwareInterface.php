<?php

namespace Qless\Jobs;

/**
 * Qless\Jobs\PerformAwareInterface
 *
 * @package Qless\Job
 */
interface PerformAwareInterface
{
    /**
     * The Job perform handler.
     *
     * @param  Job $job
     * @return void
     */
    public function perform(Job $job): void;
}

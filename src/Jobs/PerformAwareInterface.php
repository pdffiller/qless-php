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
     * @param  BaseJob $job
     * @return void
     */
    public function perform(BaseJob $job): void;
}

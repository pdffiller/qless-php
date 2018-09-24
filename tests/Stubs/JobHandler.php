<?php

namespace Qless\Tests\Stubs;

use Qless\Jobs\Job;
use Qless\Jobs\PerformAwareInterface;

/**
 * Qless\Tests\Stubs\JobHandler
 *
 * @package Qless\Tests\Stubs
 */
class JobHandler implements PerformAwareInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Job $job
     * @return void
     */
    public function perform(Job $job): void
    {
        $job->data['stack'][] = __METHOD__;

        $job->complete();
    }

    public function myPerformMethod(Job $job): void
    {
        $job->complete();
    }
}

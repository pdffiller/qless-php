<?php

namespace Qless\Tests\Stubs;

use Qless\Jobs\BaseJob;
use Qless\Jobs\PerformAwareInterface;
use Qless\Jobs\RecurringJob;

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
     * @param  BaseJob $job
     * @return void
     */
    public function perform(BaseJob $job): void
    {
        $job->data['stack'][] = __METHOD__;

        $job->complete();
    }

    public function myPerformMethod(BaseJob $job): void
    {
        $job->complete();
    }
}

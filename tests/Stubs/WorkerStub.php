<?php

namespace Qless\Tests\Stubs;

use Qless\Jobs\Job;
use Qless\Jobs\PerformAwareInterface;

/**
 * Qless\Tests\Stubs\WorkerStub
 *
 * @package Qless\Tests\Stubs
 */
class WorkerStub implements PerformAwareInterface
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
    }
}

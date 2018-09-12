<?php

namespace Qless\Tests\Stubs;

use Qless\Jobs\Job;
use Qless\Jobs\JobHandlerInterface;

/**
 * Qless\Tests\Stubs\WorkerStub
 *
 * @package Qless\Tests\Stubs
 */
class WorkerStub implements JobHandlerInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Job $job
     * @return void
     */
    public function perform(Job $job): void
    {
    }
}

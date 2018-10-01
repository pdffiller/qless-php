<?php

namespace Qless\Jobs;

use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;

/**
 * Qless\Jobs\RecurringJob
 *
 * Wraps a recurring job.
 *
 * @package Qless\Jobs
 */
class RecurringJob extends BaseJob
{
    /**
     * Sets Job's priority.
     *
     * @param  int $priority
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    protected function setJobPriority(int $priority): void
    {
        if ($this->client->call('recur.update', $this->jid, 'priority', $priority)) {
            $this->priority = $priority;
        }
    }
}

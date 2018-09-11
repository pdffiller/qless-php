<?php

namespace Qless\Demo\Enqueing;

use Qless\Job;

class MyJobClass
{
    /**
     * @param Job $job Is an instance of `Qless\Job` and provides access to
     *                 the payload data via `$job->getData()`, a means to cancel
     *                 the job (`$job->cancel()`), and more.
     */
    public function perform(Job $job): void
    {
        // ...
        echo 'Perform ', $job->getId(), ' job', PHP_EOL;
    }
}

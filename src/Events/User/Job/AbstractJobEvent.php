<?php

namespace Qless\Events\User\Job;

use Qless\Events\User\AbstractEvent;
use Qless\Jobs\BaseJob;

abstract class AbstractJobEvent extends AbstractEvent
{
    private $job;

    /**
     * AbstractJobEvent constructor.
     * @param object $source
     * @param BaseJob $job
     */
    public function __construct($source, BaseJob $job)
    {
        parent::__construct($source);
        $this->job = $job;
    }

    public static function getEntityName(): string
    {
        return 'job';
    }

    public function getJob(): BaseJob
    {
        return $this->job;
    }
}

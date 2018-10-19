<?php

namespace Qless\Events\User\Job;

use Qless\Jobs\BaseJob;

class OnFailure extends AbstractJobEvent
{
    private $group;
    private $message;

    public static function getHappening(): string
    {
        return 'onFailure';
    }

    /** @inheritdoc */
    public function __construct($source, BaseJob $job, string $group, string $message)
    {
        parent::__construct($source, $job);
        $this->group = $group;
        $this->message = $message;
    }

    public function getGroup(): string
    {
        return $this->group;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

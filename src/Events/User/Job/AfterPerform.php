<?php

namespace Qless\Events\User\Job;

class AfterPerform extends AbstractJobEvent
{
    public static function getHappening(): string
    {
        return 'afterPerform';
    }
}

<?php

namespace Qless\Events\User\Job;

class BeforePerform extends AbstractJobEvent
{
    public static function getHappening(): string
    {
        return 'beforePerform';
    }
}

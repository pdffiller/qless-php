<?php

namespace Qless\Events\User\Worker;

class AfterFork extends AbstractWorkerEvent
{
    public static function getHappening(): string
    {
        return 'afterFork';
    }
}

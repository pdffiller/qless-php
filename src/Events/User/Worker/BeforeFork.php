<?php

namespace Qless\Events\User\Worker;

class BeforeFork extends AbstractWorkerEvent
{
    public static function getHappening(): string
    {
        return 'beforeFork';
    }
}

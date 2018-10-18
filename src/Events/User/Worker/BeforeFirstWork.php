<?php

namespace Qless\Events\User\Worker;

class BeforeFirstWork extends AbstractWorkerEvent
{
    public static function getHappening(): string
    {
        return 'beforeFirstWork';
    }
}

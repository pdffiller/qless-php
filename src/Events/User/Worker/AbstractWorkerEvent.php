<?php

namespace Qless\Events\User\Worker;

use Qless\Events\User\AbstractEvent;

abstract class AbstractWorkerEvent extends AbstractEvent
{
    public static function getEntityName(): string
    {
        return 'worker';
    }
}

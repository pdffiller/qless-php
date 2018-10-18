<?php

namespace Qless\Events\User\Queue;

use Qless\Events\User\AbstractEvent;

abstract class AbstractQueueEvent extends AbstractEvent
{
    public static function getEntityName(): string
    {
        return 'queue';
    }
}

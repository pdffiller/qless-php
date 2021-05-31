<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Job as JobEvent;
use Qless\Subscribers\SignalsAwareSubscriber;
use Qless\Workers\AbstractWorker;
use RuntimeException;

interface SignalWorker
{
    public function getLastSignalAction();
}

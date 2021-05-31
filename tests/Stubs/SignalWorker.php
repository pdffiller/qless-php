<?php

namespace Qless\Tests\Stubs;

use Qless\Workers\WorkerInterface;

interface SignalWorker extends WorkerInterface
{

    public function getLastSignalAction();
}

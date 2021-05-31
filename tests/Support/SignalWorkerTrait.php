<?php

namespace Qless\Tests\Support;

use Qless\Tests\Stubs\DefaultSignalWorker;

/**
 * Qless\Tests\Support\SignalWorkerTrait
 *
 * @package Qless\Tests
 */
trait SignalWorkerTrait
{

    /**
     * @var string|null
     */
    protected $lastSignalAction = null;

    protected function setLastSignalAction(string $lastSignalAction): void
    {
        $this->lastSignalAction = $lastSignalAction;
    }

    public function getLastSignalAction(): ?string
    {
        return $this->lastSignalAction;
    }
}

<?php

namespace Qless\Workers\Traits;

/**
 * Qless\Workers\Traits\ShutdownAwareTrait
 *
 * @package Qless\Workers
 */
trait ShutdownAwareTrait
{
    /**
     * Is the worker has been halted.
     *
     * @var bool
     */
    private $shutdown = false;

    /**
     * Make a worker as a halted.
     *
     * @return void
     */
    protected function doShutdown(): void
    {
        $this->shutdown = true;
    }

    /**
     * If the worker has been halted.
     *
     * @return bool
     */
    protected function isShutdown(): bool
    {
        return $this->shutdown;
    }
}

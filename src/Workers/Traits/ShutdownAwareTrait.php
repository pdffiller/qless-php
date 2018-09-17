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
    protected $shutdown = false;

    /**
     * Make a worker as a halted.
     *
     * @return void
     */
    protected function doShutdown(): void
    {
        $this->shutdown = true;
    }
}

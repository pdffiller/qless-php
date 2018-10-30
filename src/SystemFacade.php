<?php

namespace Qless;

/**
 * Qless\SystemFacade
 *
 * @package Qless
 */
class SystemFacade
{
    /**
     * Send a signal to a process.
     *
     * @link   http://php.net/manual/en/function.posix-kill.php
     *
     * @param  int $pid The process identifier.
     * @param  int $sig One of the PCNTL signals constants.
     * @return bool
     */
    public function posixKill(int $pid, int $sig): bool
    {
        return posix_kill($pid, $sig);
    }
}

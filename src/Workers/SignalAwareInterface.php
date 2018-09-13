<?php

namespace Qless\Workers;

/**
 * Qless\Workers\SignalAwareInterface
 *
 * @package Qless\Workers
 */
interface SignalAwareInterface
{
    /**
     * Force an immediate shutdown of the worker,
     * killing any child jobs currently running.
     *
     * @return void
     */
    public function shutdownNow(): void;

    /**
     * Schedule a worker for shutdown.
     *
     * Will finish processing the current job and when the timeout interval is reached,
     * the worker will shut down.
     *
     * @return void
     */
    public function shutdown(): void;

    /**
     * Kill a forked child job immediately.
     * The job it is processing will not be completed.
     *
     * @return void
     */
    public function killChildren(): void;

    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     *
     * @return void
     */
    public function pauseProcessing(): void;

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     *
     * @return void
     */
    public function unPauseProcessing(): void;
}

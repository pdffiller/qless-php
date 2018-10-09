<?php

namespace Qless\Workers;

use Psr\Log\LoggerAwareInterface;
use Qless\Exceptions\RuntimeException;
use Qless\Jobs\BaseJob;
use Qless\Jobs\PerformAwareInterface;

/**
 * Qless\Workers\WorkerInterface
 *
 * @package Qless\Workers
 */
interface WorkerInterface extends LoggerAwareInterface
{
    /**
     * Sets the interval for checking for new jobs.
     *
     * @param  int $interval
     * @return void
     */
    public function setInterval(int $interval): void;

    /**
     * Sets the internal worker name.
     *
     * @param  string $name
     * @return void
     */
    public function setName(string $name): void;

    /**
     * Gets the internal worker name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Register the job perform handler.
     *
     * @param  PerformAwareInterface $jobHandler Instance of handler.
     * @return void
     *
     * @throws RuntimeException
     */
    public function registerJobPerformHandler(PerformAwareInterface $jobHandler): void;

    /**
     * Reserve a job to perform work.
     *
     * @return null|BaseJob
     */
    public function reserve(): ?BaseJob;

    /**
     * Starts the worker.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Set the title of the process.
     *
     * @param  string $value
     * @param  array  $context
     * @return void
     */
    public function title(string $value, array $context = []): void;

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

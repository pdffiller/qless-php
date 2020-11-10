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
interface ResourceLimitedWorkerInterface extends WorkerInterface
{
    /**
     * @param int $bytes
     */
    public function setMemoryLimit(int $bytes): void;

    /**
     * @param int $number
     */
    public function setMaximumNumberJobs(int $number): void;

    /**
     * @param int $seconds
     */
    public function setTimeLimit(int $seconds): void;
}

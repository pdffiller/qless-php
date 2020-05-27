<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;

trait ResourceLimitedWorkerTrait
{

    abstract protected function isShuttingDown(): bool;

    abstract protected function getLogger(): LoggerInterface;

    abstract public function shutdown(): void;


    /**
     * @var int
     */
    protected $maximumNumberOfJobs;

    /**
     * Time limit execution
     *
     * @var int
     */
    protected $timeLimitInSeconds;

    /**
     * Memory limit execution
     *
     * @var int
     */
    protected $memoryLimit;

    /**
     * @var int
     */
    protected $numberExecutedJobs = 0;

    /**
     * @var int
     */
    protected $endTime;

    /**
     * @param int $bytes
     */
    public function setMemoryLimit(int $bytes): void
    {
        $this->memoryLimit = $bytes;
    }

    /**
     * @param int $number
     */
    public function setMaximumNumberJobs(int $number): void
    {
        $this->maximumNumberOfJobs = $number;
    }

    /**
     * @param int $seconds
     */
    public function setTimeLimit(int $seconds): void
    {
        $this->timeLimitInSeconds = $seconds;
    }

    /**
     * Stop when job count is exceeded
     *
     * @return void
     */
    protected function stopWhenJobCountIsExceeded(): void
    {
        if ($this->maximumNumberOfJobs <= 0 || $this->isShuttingDown()) {
            return;
        }
        if (++$this->numberExecutedJobs >= $this->maximumNumberOfJobs) {
            $this->getLogger()->info(
                'Worker stopped due to maximum count of {count} exceeded',
                [
                    'count' => $this->maximumNumberOfJobs
                ]
            );
            $this->shutdown();
        }
    }

    /**
     * Stop when time limit is reached
     *
     * @return void
     */
    protected function stopWhenTimeLimitIsReached(): void
    {
        if ($this->timeLimitInSeconds === null || $this->isShuttingDown()) {
            return;
        }
        if ($this->endTime < microtime(true)) {
            $this->getLogger()->info(
                'Worker stopped due to time limit of {timeLimit}s reached',
                [
                    'timeLimit' => $this->timeLimitInSeconds
                ]
            );
            $this->shutdown();
        }
    }

    /**
     * Stop when memory usage is exceeded
     *
     * @return void
     */
    protected function stopWhenMemoryUsageIsExceeded(): void
    {
        if ($this->memoryLimit === null || $this->isShuttingDown()) {
            return;
        }
        if ($this->memoryLimit < memory_get_usage(true)) {
            $this->getLogger()->info(
                'Worker stopped due to memory limit of {limit} exceeded',
                [
                    'limit' => $this->memoryLimit
                ]
            );
            $this->shutdown();
        }
    }
}

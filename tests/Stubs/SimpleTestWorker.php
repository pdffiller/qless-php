<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Job as JobEvent;
use Qless\Workers\AbstractWorker;

class SimpleTestWorker extends AbstractWorker {

    /**
     * @var int
     */
    private $maximumNumberOfJobs;

    /**
     * Time limit execution
     *
     * @var int
     */
    private $timeLimitInSeconds;

    /**
     * @var int
     */
    private $endTime;

    /**
     * @var int
     */
    private $numberExecutedJobs = 0;

    /**
     * @param int $seconds
     */
    public function setTimeLimit(int $seconds): void
    {
        $this->timeLimitInSeconds = $seconds;
    }

    /**
     * @param int $number
     */
    public function setMaximumNumberJobs(int $number): void
    {
        $this->maximumNumberOfJobs = $number;
    }

    public function perform(): void
    {
        $this->endTime = $this->timeLimitInSeconds ? $this->timeLimitInSeconds + microtime(true) : null;

        while (true) {
            $this->stopWhenTimeLimitIsReached();

            // Don't wait on any processes if we're already in shutdown mode.
            if ($this->isShuttingDown() == true) {
                break;
            }

            $job = $this->reserve();
            if ($job === null) {
                if ($this->interval === 0) {
                    break;
                }
                usleep($this->interval * 1000000);
                continue;
            }

            try {
                $job->perform();
            } catch (\Throwable $e) {
                $loggerContext['stack'] = $e->getMessage();
                $this->logger->critical('{type}: job {job} has failed {stack}', $loggerContext);

                $job->fail(
                    'system:fatal',
                    sprintf('%s: %s in %s on line %d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine())
                );
            }
            $this->stopWhenJobCountIsExceeded();
        }
    }

    /**
     * Stop when job count is exceeded
     *
     * @return void
     */
    private function stopWhenJobCountIsExceeded(): void
    {
        if ($this->isShuttingDown() || !($this->maximumNumberOfJobs > 0)) {
            return;
        }
        if (++$this->numberExecutedJobs >= $this->maximumNumberOfJobs) {
            $this->logger->info('Worker stopped due to maximum count of {count} exceeded', [
                'count' => $this->maximumNumberOfJobs
            ]);
            $this->shutdown();
        }
    }

    /**
     * Stop when time limit is reached
     *
     * @return void
     */
    private function stopWhenTimeLimitIsReached(): void
    {
        if ($this->isShuttingDown() || $this->timeLimitInSeconds === null) {
            return;
        }
        if ($this->endTime < microtime(true)) {
            $this->logger->info('Worker stopped due to time limit of {timeLimit}s reached', [
                'timeLimit' => $this->timeLimitInSeconds
            ]);
            $this->shutdown();
        }
    }

}

<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;
use Qless\Client;
use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\ReserverInterface;

trait JobLoopWorkerTrait
{
    use ResourceLimitedWorkerTrait;

    abstract protected function getLogger(): LoggerInterface;

    abstract public function isPaused(): bool;

    abstract public function title(string $value, array $context = []): void;

    abstract public function reserve(): ?BaseJob;

    abstract public function setCurrentJob(BaseJob $job = null): void;

    abstract public function getInterval(): int;

    abstract public function getName(): string;

    /** @var array */
    protected $logContext = [];

    /**
     * Perform work in a loop.
     *
     * @param Client $client
     * @param ReserverInterface $reserver
     * @param string|null $logPrefix
     */
    protected function doJobLoop(Client $client, ReserverInterface $reserver, ?string $logPrefix = null): void
    {
        declare(ticks=1);

        $this->endTime = $this->timeLimitInSeconds ? $this->timeLimitInSeconds + microtime(true) : null;
        $this->getLogger()->info($logPrefix . 'worker started', $this->logContext);

        $reserver->beforeWork();

        $didWork = false;

        while (true) {
            $this->stopWhenTimeLimitIsReached();

            // Don't wait on any processes if we're already in shutdown mode.
            if ($this->isShuttingDown() === true) {
                break;
            }

            while ($this->isPaused()) {
                usleep(250000);
            }

            if ($didWork) {
                $this->title(
                    sprintf(
                        'Waiting for %s with interval %d sec',
                        implode(',', $reserver->getQueues()),
                        $this->getInterval()
                    )
                );
                $didWork = false;
            }

            $job = $this->reserve();
            if ($job === null) {
                if ($this->getInterval() === 0) {
                    break;
                }
                usleep($this->getInterval() * 1000000);
                continue;
            }

            $this->setCurrentJob($job);
            $this->logContext['job.identifier'] = $job->jid;

            $this->performWork($job);
            $this->setCurrentJob(null);
            $this->logContext['job'] = null;
            $didWork = true;

            $this->stopWhenJobCountIsExceeded();
            $this->stopWhenMemoryUsageIsExceeded();
        }
        $this->getLogger()->info('Deregistering worker {name}', ['name' => $this->getName()]);
        $client->getWorkers()->remove($this->getName());
    }

    /**
     * @param BaseJob $job
     */
    abstract protected function performWork(BaseJob $job): void;

}

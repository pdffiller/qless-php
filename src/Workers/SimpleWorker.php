<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;
use Qless\Events\User\Worker as WorkerEvent;
use Qless\Jobs\BaseJob;
use Qless\Subscribers\SignalsAwareSubscriber;

/**
 * Qless\Workers\SimpleWorker
 *
 * @package Qless\Workers
 */
final class SimpleWorker extends AbstractWorker
{
    use JobLoopWorkerTrait;

    /**
     * @param BaseJob $job
     */
    protected function performWork(BaseJob $job): void
    {
        $this->title(sprintf('Processing %s since %s', $job->jid, strftime('%F %T')));

        $this->performJob($job, $this->logContext);
    }

    /**
     * @inheritDoc
     */
    public function perform(): void
    {
        $this->doJobLoop($this->client, $this->reserver);
    }
}

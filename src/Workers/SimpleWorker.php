<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;
use Qless\Events\User\Worker as WorkerEvent;
use Qless\Exceptions\SimpleWorkerContinuationException;
use Qless\Jobs\BaseJob;
use Qless\Subscribers\SignalsAwareSubscriber;

/**
 * Qless\Workers\SimpleWorker
 *
 * @package Qless\Workers
 */
final class SimpleWorker extends AbstractWorker implements ResourceLimitedWorkerInterface
{
    use JobLoopWorkerTrait;

    /** @var SignalsAwareSubscriber */
    private $signalsSubscriber;

    /**
     * {@inheritdoc}
     */
    public function onConstruct(): void
    {
        $this->signalsSubscriber = new SignalsAwareSubscriber($this->logger);
        $this->getEventsManager()->attach(WorkerEvent\AbstractWorkerEvent::getEntityName(), $this->signalsSubscriber);
    }

    /**
     * @inheritdoc
     */
    public function setLogger(LoggerInterface $logger): void
    {
        parent::setLogger($logger);

        $this->signalsSubscriber->setLogger($logger);
    }

    /**
     * @inheritdoc
     */
    protected function performWork(BaseJob $job): void
    {
        $this->title(sprintf('Processing %s since %s', $job->jid, strftime('%F %T')));

        try {
            $this->performJob($job, $this->logContext);
        } catch (SimpleWorkerContinuationException $exception) {
            return;
        }
    }

    /**
     * @inheritDoc
     */
    public function perform(): void
    {
        $this->logContext = ['type' => $this->name];
        $this->doJobLoop($this->client, $this->reserver, '{type}: ');
    }

    /**
     * @inheritdoc
     * @throws SimpleWorkerContinuationException
     */
    public function killChildren(): void
    {
        if ($this->isShuttingDown()) {
            $this->signalsSubscriber->afterFork(new WorkerEvent\AfterFork($this));
            posix_kill(\posix_getpid(), SIGINT);
            return;
        }

        $this->logger->notice('{type}: Abandoning current job progress in worker', ['type' => $this->name]);

        throw new SimpleWorkerContinuationException('Caught Signal', \SIGUSR1);
    }
}

<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Job as JobEvent;
use Qless\Subscribers\SignalsAwareSubscriber;
use Qless\Tests\Support\SignalWorkerTrait;
use Qless\Workers\AbstractWorker;
use RuntimeException;

/**
 * Qless\Tests\Stubs\DefaultSignalWorker
 *
 * @package Qless\Tests\Stubs
 */
class DefaultSignalWorker extends AbstractWorker implements SignalWorker
{

    use SignalWorkerTrait;

    /** @var SignalsAwareSubscriber */
    private $signalsSubscriber;

    public function onConstruct(): void
    {
        $this->signalsSubscriber = new SignalsAwareSubscriber($this->logger);
    }

    /**
     * {@inheritdoc}
     */
    public function perform(): void
    {
    }


    public function shutdownNow(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function shutdown(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function killChildren(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function pauseProcessing(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function unPauseProcessing(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

}

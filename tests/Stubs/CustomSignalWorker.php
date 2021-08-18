<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Worker\AbstractWorkerEvent;
use Qless\Subscribers\SignalsAwareSubscriber;
use Qless\Tests\Support\SignalWorkerTrait;
use Qless\Workers\AbstractWorker;

/**
 * Qless\Tests\Stubs\CustomSignalWorker
 *
 * @package Qless\Tests\Stubs
 */
class CustomSignalWorker extends AbstractWorker implements SignalWorker
{

    use SignalWorkerTrait;

    public function onConstruct(): void
    {
        $this->getEventsManager()->attach(
            AbstractWorkerEvent::getEntityName(),
            new SignalsAwareSubscriber($this->logger)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function perform(): void
    {
    }

    public function doSigTerm(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function doSigInt(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function doSigQuit(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function doSigUsr1(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function doSigUsr2(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    public function doSigCont(): void
    {
        $this->setLastSignalAction(__FUNCTION__);
    }

    /**
     * {@inheritDoc}
     */
    public function handleSignal(int $signal, string $signalName, SignalsAwareSubscriber $signalsAwareSubscriber): void
    {
        switch ($signal) {
            case SIGTERM:
                $this->doSigTerm();
                break;

            case SIGINT:
                $this->doSigInt();
                break;

            case SIGQUIT:
                $this->doSigQuit();
                break;

            case SIGUSR1:
                $this->doSigUsr1();
                break;

            case SIGUSR2:
                $this->doSigUsr2();
                break;

            case SIGCONT:
                $this->doSigCont();
                break;
        }
    }
}

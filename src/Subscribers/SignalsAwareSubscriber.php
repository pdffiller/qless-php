<?php

namespace Qless\Subscribers;

use Psr\Log\LoggerInterface;
use Qless\Events\User\Worker as WorkerEvent;
use Qless\Signals\SignalHandler;
use Qless\Workers\WorkerInterface;

/**
 * Qless\Subscribers\SignalsAwareSubscriber
 *
 * @package Qless\Subscribers
 */
class SignalsAwareSubscriber
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * SignalsAwareSubscriber constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function beforeFirstWork(WorkerEvent\BeforeFirstWork $event): void
    {
        /**
         * Do not use declare(ticks=1) instead use pcntl_async_signals(true)
         * There's no performance hit or overhead with `pcntl_async_signals()`.
         *
         * @link https://blog.pascal-martin.fr/post/php71-en-other-new-things.html
         */
        pcntl_async_signals(true);

        $this->registerSignalHandler($event->getSource());
    }

    public function afterFork(WorkerEvent\AfterFork $event): void
    {
        $this->clearSignalHandler();
    }

    /**
     * Register a signal handler.
     *
     * TERM: Shutdown immediately and stop processing jobs (quick shutdown).
     * INT:  Shutdown immediately and stop processing jobs (quick shutdown).
     * QUIT: Shutdown after the current job finishes processing (graceful shutdown).
     * USR1: Kill the forked child immediately and continue processing jobs.
     * USR2: Pausing job processing.
     * CONT: Resumes worker allowing it to pick.
     *
     * @link   http://man7.org/linux/man-pages/man7/signal.7.html
     *
     * @param  WorkerInterface $worker
     * @return void
     */
    protected function registerSignalHandler(WorkerInterface $worker): void
    {
        $this->logger->info('Register a signal handler that a worker should respond to');

        SignalHandler::create(
            SignalHandler::KNOWN_SIGNALS,
            function (int $signal, string $signalName) use ($worker) {
                $this->logger->info('{type}: Was received recognized signal {signal}', [
                    'type'   => $worker->getName(),
                    'signal' => $signalName,
                ]);

                switch ($signal) {
                    case SIGTERM:
                        $worker->shutDownNow();
                        break;
                    case SIGINT:
                        $worker->shutDownNow();
                        break;
                    case SIGQUIT:
                        $worker->shutdown();
                        break;
                    case SIGUSR1:
                        $worker->killChildren();
                        break;
                    case SIGUSR2:
                        $worker->pauseProcessing();
                        break;
                    case SIGCONT:
                        $worker->unPauseProcessing();
                        break;
                }
            }
        );
    }

    /**
     * Clear all previously registered signal handlers.
     *
     * @return void
     */
    protected function clearSignalHandler(): void
    {
        SignalHandler::unregister();
    }
}

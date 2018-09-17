<?php

namespace Qless\Workers\Traits;

use Qless\Signals\SignalHandler;

/**
 * Qless\Workers\Traits\SignalAwareTrait
 *
 * @package Qless\Workers
 */
trait SignalAwareTrait
{
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
     * @return void
     */
    protected function registerSignalHandler(): void
    {
        $this->logger->info('Register a signal handler that a worker should respond to.');

        SignalHandler::create(
            SignalHandler::KNOWN_SIGNALS,
            function (int $signal, string $signalName) {
                $this->logger->info("Was received known signal '{signal}'.", ['signal' => $signalName]);

                switch ($signal) {
                    case SIGTERM:
                        $this->shutDownNow();
                        break;
                    case SIGINT:
                        $this->shutDownNow();
                        break;
                    case SIGQUIT:
                        $this->shutdown();
                        break;
                    case SIGUSR1:
                        $this->killChildren();
                        break;
                    case SIGUSR2:
                        $this->pauseProcessing();
                        break;
                    case SIGCONT:
                        $this->unPauseProcessing();
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

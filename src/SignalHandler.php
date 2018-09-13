<?php

namespace Qless;

use Qless\Workers\SignalAwareInterface;

/**
 * Qless\SignalHandler
 *
 * @package Qless
 */
class SignalHandler
{
    /** @var SignalAwareInterface */
    private $worker;

    /**
     * SignalHandler constructor.
     *
     * @param SignalAwareInterface $worker
     */
    public function __construct(SignalAwareInterface $worker)
    {
        $this->worker = $worker;
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs (quick shutdown).
     * INT:  Shutdown immediately and stop processing jobs (quick shutdown).
     * QUIT: Shutdown after the current job finishes processing (graceful shutdown).
     * USR1: Kill the forked child immediately and continue processing jobs.
     * USR2: Pausing job processing.
     * CONT: Resumes worker allowing it to pick.
     *
     * @link http://man7.org/linux/man-pages/man7/signal.7.html
     *
     * @return void
     */
    public function register(): void
    {
        pcntl_signal(SIGTERM, [$this->worker, 'shutDownNow'], false);
        pcntl_signal(SIGINT, [$this->worker, 'shutDownNow'], false);
        pcntl_signal(SIGQUIT, [$this->worker, 'shutdown'], false);
        pcntl_signal(SIGUSR1, [$this->worker, 'killChildren'], false);
        pcntl_signal(SIGUSR2, [$this->worker, 'pauseProcessing'], false);
        pcntl_signal(SIGCONT, [$this->worker, 'unPauseProcessing'], false);
    }

    /**
     * Clear all previously registered signal handlers.
     *
     * @return void
     */
    public function unregister(): void
    {
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGQUIT, SIG_DFL);
        pcntl_signal(SIGUSR1, SIG_DFL);
        pcntl_signal(SIGUSR2, SIG_DFL);
        pcntl_signal(SIGCONT, SIG_DFL);
    }
}

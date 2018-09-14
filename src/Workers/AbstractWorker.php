<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Client;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\RuntimeException;
use Qless\Jobs\Job;
use Qless\Jobs\JobHandlerInterface;
use Qless\Jobs\Reservers\ReserverInterface;
use Qless\Signal\SignalHandler;

/**
 * Qless\Workers\AbstractWorker
 *
 * @package Qless\Workers
 */
abstract class AbstractWorker implements SignalAwareInterface
{
    protected const KNOWN_SIGNALS = [
        SIGTERM,
        SIGINT,
        SIGQUIT,
        SIGUSR1,
        SIGUSR2,
        SIGCONT,
    ];

    /**
     * The interval for checking for new jobs.
     *
     * @var int
     */
    protected $interval = 5;

    /**
     * The internal client to communicate with qless-core.
     *
     * @var Client
     */
    protected $client;

    /**
     * The job reserver.
     *
     * @var ReserverInterface
     */
    protected $reserver;

    /**
     * The internal logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Internal worker name.
     *
     * @var string
     */
    protected $name;

    /**
     * The fully qualified class name of the job perform handler.
     *
     * @var ?string
     */
    protected $jobPerformClass = null;

    /**
     * Is the worker has been halted.
     *
     * @var bool
     */
    protected $shutdown = false;

    /**
     * The internal signal handler.
     *
     * @var SignalHandler
     */
    protected $signalHandler;

    /**
     * Worker constructor.
     *
     * @param ReserverInterface $reserver
     * @param Client            $client
     * @param string|null       $name
     */
    public function __construct(
        ReserverInterface $reserver,
        Client $client,
        ?string $name = null
    ) {
        $this->reserver = $reserver;
        $this->logger = new NullLogger();
        $this->client = $client;

        $this->setName($name);
    }

    /**
     * Sets the interval for checking for new jobs.
     *
     * @param  int $interval
     * @return void
     */
    final public function setInterval(int $interval): void
    {
        $this->interval = $interval;
    }

    /**
     * Sets the internal logger.
     *
     * @param  LoggerInterface $logger
     * @return void
     */
    final public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Initialize internal worker name.
     *
     * @param  null|string $name
     * @return void
     */
    protected function setName(?string $name = null)
    {
        $this->name = $name ?: substr(md5($this->reserver->getDescription()), 0, 16);
    }

    /**
     * Register the job perform handler.
     *
     * @param  string $jobPerformClass The fully qualified class name.
     * @return void
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function registerJobPerformHandler(string $jobPerformClass): void
    {
        if (!class_exists($jobPerformClass)) {
            throw new RuntimeException(
                sprintf('Could not find job perform class %s.', $jobPerformClass)
            );
        }

        $interfaces = class_implements($jobPerformClass);
        if (in_array(JobHandlerInterface::class, $interfaces, true) == false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided Job class "%s" does not implement %s interface.',
                    $jobPerformClass,
                    JobHandlerInterface::class
                )
            );
        }

        $this->jobPerformClass = $jobPerformClass;
    }

    /**
     * Reserve a job to perform work.
     *
     * @return null|Job
     */
    final public function reserve(): ?Job
    {
        return $this->reserver->reserve();
    }

    /**
     * Starts the worker.
     *
     * @return void
     */
    abstract public function run(): void;

    /**
     * This method should be called before worker run.
     *
     * @return void
     */
    protected function onStartup(): void
    {
        $this->registerSignalHandler();
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
     * @return void
     */
    protected function registerSignalHandler(): void
    {
        $this->logger->info('Register a signal handler that a worker should respond to.');

        $this->signalHandler = SignalHandler::register(
            self::KNOWN_SIGNALS,
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
        $this->signalHandler->unregister(self::KNOWN_SIGNALS);
    }

    /**
     * Make a worker as a halted.
     *
     * @return void
     */
    protected function doShutdown(): void
    {
        $this->shutdown = true;
    }
}

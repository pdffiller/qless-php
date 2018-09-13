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
use Qless\SignalHandler;

/**
 * Qless\Workers\AbstractWorker
 *
 * @package Qless\Workers
 */
abstract class AbstractWorker implements SignalAwareInterface
{
    /**
     * The interval for checking for new jobs.
     *
     * @var int
     */
    protected $interval = 60;

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

    /** @var SignalHandler */
    protected $shutdownHandler;

    /**
     * Worker constructor.
     *
     * @param ReserverInterface    $reserver
     * @param Client               $client
     * @param LoggerInterface|null $logger
     * @param SignalHandler|null   $shutdownHandler
     * @param string|null          $name
     */
    public function __construct(
        ReserverInterface $reserver,
        Client $client,
        LoggerInterface $logger = null,
        SignalHandler $shutdownHandler = null,
        ?string $name = null
    ) {
        $this->reserver = $reserver;
        $this->logger = $logger ?: new NullLogger();
        $this->client = $client;
        $this->shutdownHandler = $shutdownHandler ?: new SignalHandler($this);

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
     * Register signal handler.
     *
     * @return void
     */
    protected function registerSignalHandler(): void
    {
        $this->shutdownHandler->register();
    }

    /**
     * Clear all previously registered signal handlers.
     *
     * @return void
     */
    protected function clearSignalHandler(): void
    {
        $this->shutdownHandler->unregister();
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

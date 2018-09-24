<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Client;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\RuntimeException;
use Qless\Jobs\Job;
use Qless\Jobs\PerformAwareInterface;
use Qless\Jobs\PerformHandlerFactory;
use Qless\Jobs\Reservers\ReserverInterface;

/**
 * Qless\Workers\AbstractWorker
 *
 * @package Qless\Workers
 */
abstract class AbstractWorker implements WorkerInterface, EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

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
    protected $jobPerformClass;

    /** @var PerformHandlerFactory */
    private $performHandlerFactory;

    /**
     * Worker constructor.
     *
     * @param ReserverInterface $reserver
     * @param Client            $client
     */
    final public function __construct(ReserverInterface $reserver, Client $client)
    {
        $this->reserver = $reserver;
        $this->logger = new NullLogger();
        $this->client = $client;
        $this->name = array_values(array_slice(explode('\\', get_class($this)), -1))[0];

        $this->setEventsManager($client->getEventsManager());

        $this->performHandlerFactory = new PerformHandlerFactory();
        $this->performHandlerFactory->setEventsManager($client->getEventsManager());

        $this->onConstruct();
    }

    /**
     * On construct internal event.
     *
     * @return void
     */
    public function onConstruct(): void
    {
    }

    /**
     * Get perform handler factory.
     *
     * @return PerformHandlerFactory
     */
    public function getPerformHandlerFactory(): PerformHandlerFactory
    {
        return $this->performHandlerFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @param  int $interval
     * @return void
     */
    final public function setInterval(int $interval): void
    {
        $this->interval = abs($interval);
    }

    /**
     * {@inheritdoc}
     *
     * @param  LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $name
     * @return void
     */
    final public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $jobPerformClass The fully qualified class name.
     * @return void
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    final public function registerJobPerformHandler(string $jobPerformClass): void
    {
        if (!class_exists($jobPerformClass)) {
            throw new RuntimeException(
                sprintf('Could not find job perform class %s.', $jobPerformClass)
            );
        }

        $interfaces = class_implements($jobPerformClass);
        if (in_array(PerformAwareInterface::class, $interfaces, true) == false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided Job class "%s" does not implement %s interface.',
                    $jobPerformClass,
                    PerformAwareInterface::class
                )
            );
        }

        $this->jobPerformClass = $jobPerformClass;
    }

    /**
     * {@inheritdoc}
     *
     * @return null|Job
     */
    final public function reserve(): ?Job
    {
        return $this->reserver->reserve();
    }

    /**
     * {@inheritdoc}
     *
     * @link   http://php.net/manual/en/function.setproctitle.php
     *
     * @param  string $value
     * @param  array $context
     * @return void
     */
    final public function title(string $value, array $context = []): void
    {
        $this->logger->info($value, $context);

        $line = sprintf('qless-php-worker %s', $value);

        if (function_exists('setproctitle')) {
            \setproctitle($line);
            return;
        }

        if (@cli_set_process_title($line) === false) {
            if ('Darwin' === PHP_OS) {
                trigger_error(
                    'Running "cli_get_process_title" as an unprivileged user is not supported on macOS.',
                    E_USER_WARNING
                );
            } else {
                $error = error_get_last();
                trigger_error($error['message'], E_USER_WARNING);
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    final public function run(): void
    {
        $this->getEventsManager()->fire('worker:beforeFirstFork', $this);

        $this->perform();
    }

    abstract public function perform(): void;
}

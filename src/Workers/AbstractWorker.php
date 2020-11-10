<?php

namespace Qless\Workers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Client;
use Qless\Events\User\Job as JobEvent;
use Qless\Events\User\Worker as WorkerEvent;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Jobs\BaseJob;
use Qless\Jobs\PerformAwareInterface;
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
     * Logging object that implements the PSR-3 LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * String identifying this worker.
     *
     * @var string
     */
    protected $name;

    /** @var PerformAwareInterface|null */
    protected $jobPerformHandler;

    /**
     * Current job instance (if is set).
     *
     * @var BaseJob|null
     */
    protected $job;

    /**
     * Is current iteration paused?
     *
     * @var bool
     */
    protected $paused = false;

    /**
     * Is the worker has been halted.
     *
     * @var bool
     */
    private $shutdown = false;

    /**
     * Instantiate a new worker, given a list of queues that it should be working on.
     *
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

        $this->onConstruct();
    }

    /**
     * Sets current job.
     *
     * @param  BaseJob|null $job
     * @return void
     */
    final public function setCurrentJob(BaseJob $job = null): void
    {
        $this->job = $job;
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

    /**
     * If the worker has been halted.
     *
     * @return bool
     */
    protected function isShuttingDown(): bool
    {
        return $this->shutdown;
    }

    /**
     * On construct internal event.
     *
     * @return void
     */
    public function onConstruct(): void
    {
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
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

    final public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * {@inheritdoc}
     *
     * @param  LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->reserver->setLogger($logger);
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
     * @return string
     */
    final public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param  PerformAwareInterface $jobHandler Instance of handler
     * @return void
     */
    final public function registerJobPerformHandler(PerformAwareInterface $jobHandler): void
    {
        $this->jobPerformHandler = $jobHandler;
        if ($this->jobPerformHandler instanceof EventsManagerAwareInterface) {
            $this->jobPerformHandler->setEventsManager($this->client->getEventsManager());
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return null|BaseJob
     */
    final public function reserve(): ?BaseJob
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

        $line = sprintf('Qless PHP: %s', $value);

        if (function_exists('setproctitle')) {
            \setproctitle($line);
            return;
        }

        if (@cli_set_process_title($line) === false) {
            if ('Darwin' === PHP_OS) {
                trigger_error(
                    'Running "cli_set_process_title" as an unprivileged user is not supported on macOS.',
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
     */
    final public function run(): void
    {
        $this->getEventsManager()->fire(new WorkerEvent\BeforeFirstWork($this));

        $this->perform();
    }

    /**
     * Start doing work.
     *
     * @return void
     */
    abstract public function perform(): void;

    /**
     * {@inheritdoc}
     */
    public function shutdown(): void
    {
        $this->logger->notice('{type}: QUIT received; shutting down', ['type' => $this->name]);

        $this->doShutdown();
    }

    /**
     * {@inheritdoc}
     */
    public function pauseProcessing(): void
    {
        $this->logger->notice('{type}: USR2 received; pausing job processing', ['type' => $this->name]);
        $this->paused = true;
    }

    /**
     * {@inheritdoc}
     */
    public function unPauseProcessing(): void
    {
        $this->logger->notice('{type}: CONT received; resuming job processing', ['type' => $this->name]);
        $this->paused = false;
    }

    /**
     * @return bool
     */
    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * {@inheritdoc}
     */
    public function shutdownNow(): void
    {
        $this->logger->notice('{type}: Force an immediate shutdown of the worker', ['type' => $this->name]);

        $this->doShutdown();
        $this->killChildren();
    }

    /**
     * {@inheritdoc}
     *
     * @codeCoverageIgnoreStart
     */
    public function killChildren(): void
    {
        // nothing to do
    }
    // @codeCoverageIgnoreEnd

    /**
     * @param BaseJob $job
     * @param array $loggerContext
     * @param string $loggerPrefix
     */
    protected function performJob(BaseJob $job, array $loggerContext, ?string $loggerPrefix = null): void
    {
        try {
            if ($this->jobPerformHandler) {
                if ($this->jobPerformHandler instanceof EventsManagerAwareInterface) {
                    $this->jobPerformHandler->setEventsManager($this->client->getEventsManager());
                }

                if (method_exists($this->jobPerformHandler, 'setUp')) {
                    $this->jobPerformHandler->setUp();
                }

                $this->getEventsManager()->fire(new JobEvent\BeforePerform($this->jobPerformHandler, $job));
                $this->jobPerformHandler->perform($job);
                $this->getEventsManager()->fire(new JobEvent\AfterPerform($this->jobPerformHandler, $job));

                if (method_exists($this->jobPerformHandler, 'tearDown')) {
                    $this->jobPerformHandler->tearDown();
                }
            } else {
                $job->perform();
            }

            $this->logger->notice($loggerPrefix . 'job {job} has finished', $loggerContext);
        } catch (\Throwable $e) {
            $loggerContext['stack'] = $e->getMessage();
            $this->logger->critical($loggerPrefix . 'job {job} has failed {stack}', $loggerContext);

            $job->fail(
                'system:fatal',
                sprintf('%s: %s in %s on line %d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine())
            );
        }
    }
}

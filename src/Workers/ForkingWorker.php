<?php

namespace Qless\Workers;

use Closure;
use Psr\Log\LoggerInterface;
use Qless\Events\User\Job as JobEvent;
use Qless\Events\User\Worker as WorkerEvent;
use Qless\EventsManagerAwareInterface;
use Qless\Exceptions\ErrorFormatter;
use Qless\Exceptions\RuntimeException;
use Qless\Jobs\BaseJob;
use Qless\Signals\SignalHandler;
use Qless\Subscribers\SignalsAwareSubscriber;
use Qless\Subscribers\WatchdogSubscriber;

/**
 * Qless\Workers\ForkingWorker
 *
 * @todo This class is still needs to be refactored.
 *
 * @package Qless\Workers
 */
final class ForkingWorker extends AbstractWorker
{
    private const PROCESS_TYPE_MASTER = 0;
    private const PROCESS_TYPE_JOB = 1;
    private const PROCESS_TYPE_WATCHDOG = 2;

    /** @var int */
    private $processType = self::PROCESS_TYPE_MASTER;

    /**
     * Process ID of child worker processes.
     * @var ?int
     */
    private $childPID = null;

    /** @var ?int */
    private $watchdogPID = null;

    /** @var int */
    private $childProcesses = 0;

    /** @var string */
    private $who = 'master';

    /** @var array */
    private $logContext = [];

    /** @var resource[] */
    private $sockets = [];

    /** @var SignalsAwareSubscriber */
    private $signalsSubscriber;

    /**
     * @var int
     */
    private $numberExecutedJobs = 0;

    /**
     * @var float|null
     */
    private $endTime;

    /**
     * Memory limit execution
     *
     * @var int
     */
    private $memoryLimit;

    /**
     * @var int
     */
    private $maximumNumberOfJobs;

    /**
     * Time limit execution
     *
     * @var int
     */
    private $timeLimitInSeconds;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function onConstruct(): void
    {
        $this->signalsSubscriber = new SignalsAwareSubscriber($this->logger);
        $this->getEventsManager()->attach(WorkerEvent\AbstractWorkerEvent::getEntityName(), $this->signalsSubscriber);
    }

    /**
     * {@inheritdoc}
     *
     * @param  LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        parent::setLogger($logger);

        $this->signalsSubscriber->setLogger($logger);
    }

    /**
     * @param int $bytes
     */
    public function setMemoryLimit(int $bytes): void
    {
        $this->memoryLimit = $bytes;
    }

    /**
     * @param int $seconds
     */
    public function setTimeLimit(int $seconds): void
    {
        $this->timeLimitInSeconds = $seconds;
    }

    /**
     * @param int $number
     */
    public function setMaximumNumberJobs(int $number): void
    {
        $this->maximumNumberOfJobs = $number;
    }

    /**
     * @return void
     */
    public function perform(): void
    {
        declare(ticks=1);

        $this->endTime = $this->timeLimitInSeconds ? $this->timeLimitInSeconds + microtime(true) : null;
        $this->who = 'master:' . $this->name;
        $this->logContext = ['type' => $this->who, 'job.identifier' => null];
        $this->logger->info('{type}: worker started', $this->logContext);

        $this->reserver->beforeWork();

        $didWork = false;

        while (true) {
            $this->stopWhenTimeLimitIsReached();

            // Don't wait on any processes if we're already in shutdown mode.
            if ($this->isShuttingDown() === true) {
                break;
            }

            while ($this->paused) {
                usleep(250000);
            }

            if ($didWork) {
                $this->title(
                    sprintf(
                        'Waiting for %s with interval %d sec',
                        implode(',', $this->reserver->getQueues()),
                        $this->interval
                    )
                );
                $didWork = false;
            }

            $job = $this->reserve();
            if ($job === null) {
                if ($this->interval === 0) {
                    break;
                }
                usleep($this->interval * 1000000);
                continue;
            }

            $this->setCurrentJob($job);
            $this->logContext['job.identifier'] = $job->jid;

            // fork processes
            $this->childStart();
            $this->watchdogStart($this->client->createSubscriber(['ql:log']));

            $this->title(sprintf('Forked %d at %s', $this->childPID, strftime('%F %T')));

            // Parent process, sit and wait
            while ($this->childProcesses > 0) {
                $status = null;
                $pid   = pcntl_wait($status, WUNTRACED);
                if ($pid > 0) {
                    if ($pid === $this->childPID) {
                        $exited = $this->childProcessStatus($status);
                    } elseif ($pid === $this->watchdogPID) {
                        $exited = $this->watchdogProcessStatus($status);
                    } else {
                        if ($this->isShuttingDown()) {
                            break;
                        }
                        // unexpected?
                        $this->logger->info(sprintf("master received status for unknown PID %d; exiting\n", $pid));
                        exit(1);
                    }

                    if ($exited) {
                        --$this->childProcesses;
                        switch ($pid) {
                            case $this->childPID:
                                $this->childPID = null;
                                if ($this->watchdogPID) {
                                    // shutdown watchdog immediately if child has exited
                                    posix_kill($this->watchdogPID, SIGKILL);
                                }
                                break;

                            case $this->watchdogPID:
                                $this->watchdogPID = null;
                                break;
                        }
                    }
                }
            }

            foreach ($this->sockets as $socket) {
                socket_close($socket);
            }

            $this->sockets  = [];
            $this->setCurrentJob(null);
            $this->logContext['job.identifier'] = null;
            $didWork = true;

            $this->stopWhenJobCountIsExceeded();
            $this->stopWhenMemoryUsageIsExceeded();

            /**
             * We need to reconnect due to bug in Redis library that always sends QUIT on destruction of \Redis
             * rather than just leaving socket around. This call will sometimes generate a broken pipe notice
             */
            $old = error_reporting();
            error_reporting($old & ~E_NOTICE);
            try {
                $this->client->reconnect();
            } finally {
                error_reporting($old);
            }
        }

        $this->logger->info("Deregistering worker {name}", ['name' => $this->name]);
        $this->client->getWorkers()->remove($this->name);
    }

    /**
     * Forks and creates a socket pair for communication between parent and child process
     *
     * @param  resource $socket
     * @return int PID if master or 0 if child
     *
     * @throws RuntimeException
     */
    private function fork(&$socket): int
    {
        $pair = [];

        $domain = (stripos(PHP_OS, 'WIN') === 0 ? AF_INET : AF_UNIX);
        if (\socket_create_pair($domain, SOCK_STREAM, 0, $pair) === false) {
            $error = socket_strerror(socket_last_error($pair[0] ?? null));

            $this->logger->error('{type}: unable to create socket pair; ' . $error, $this->logContext);
            exit(0);
        }

        // Fork child worker.
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('Unable to fork child worker.');
        }

        if ($pid !== 0) {
            // MASTER
            $this->childProcesses++;

            $socket = $pair[0];
            socket_close($pair[1]);

            // wait up to 10ms to receive data
            socket_set_option(
                $socket,
                SOL_SOCKET,
                SO_RCVTIMEO,
                ['sec' => 0, 'usec' => 10000]
            );

            return $pid;
        }

        $socket = $pair[1];
        socket_close($pair[0]);

        register_shutdown_function($this->handleChildErrors($socket));

        return $pid;
    }

    /**
     * A shutdown function for the forked process.
     *
     * NOTE: Shutdown functions will not be executed if the process is killed with a SIGTERM or SIGKILL signal.
     *
     * @param  resource $socket
     * @return Closure
     */
    private function handleChildErrors(&$socket): Closure
    {
        // This storage is freed on error (case of allowed memory exhausted).
        $reserved = str_repeat('*', 32 * 1024);

        return function () use (&$reserved, &$socket): void {
            unset($reserved);

            $error = error_get_last();

            if ($error === null) {
                return;
            }

            $handler = new ErrorFormatter();
            if ($handler->constant($error['type']) === null) {
                $this->logger->warning(
                    '{type}: Unable to recognize error type. Skip sending error to master: {message}',
                    $this->logContext + ['message' => $error['message']]
                );
                return;
            }

            if (is_resource($socket) === false) {
                $this->logger->warning(
                    '{type}: supplied resource is not a valid socket resource. Skip sending error to master: {message}',
                    $this->logContext + ['message' => $error['message']]
                );
                return;
            }

            $this->logger->debug('{type}: sending error to master', $this->logContext);
            $data = serialize($error);

            do {
                $len = socket_write($socket, $data);
                if ($len === false || $len === 0) {
                    break;
                }

                $data = substr($data, $len);
            } while (is_numeric($len) && $len > 0 && is_resource($socket));
        };
    }

    /**
     * Tries to create an error message from the socket.
     *
     * @param  resource $socket
     * @return null|string
     */
    private function readErrorFromSocket($socket): ?string
    {
        $error = '';
        while (!empty($res = socket_read($socket, 8192))) {
            $error .= $res;
        }

        $error = unserialize($error);

        if (is_array($error)) {
            $handler = new ErrorFormatter();

            return sprintf(
                '%s: %s in %s on line %d',
                $handler->constant($error['type']) ?: 'Unknown',
                $error['message'],
                $error['file'],
                $error['line']
            );
        }

        return null;
    }

    /**
     * Handle process exit status.
     *
     * @param int $pid
     * @param int $childType
     * @param int $exitStatus
     *
     * @return false|string FALSE if exit status indicates success; otherwise, a string containing the error messages.
     */
    private function handleProcessExitStatus(int $pid, int $childType, int $exitStatus)
    {
        switch ($childType) {
            case self::PROCESS_TYPE_JOB:
                $childTypeName = 'Child';
                break;
            default:
                $childTypeName = 'Watchdog';
        }

        if ($exitStatus === 0) {
            $this->logger->debug("{type}: {$childTypeName} process exited successfully", $this->logContext);
            return false;
        }

        $error = $this->readErrorFromSocket($this->sockets[$pid]);
        $jobFailedMessage = $error ?: "{$childTypeName} process failed with status: {$exitStatus}";

        $this->logger->error("{type}: fatal error in {$childTypeName} process: {$jobFailedMessage}", $this->logContext);

        return $jobFailedMessage;
    }

    private function childStart(): void
    {
        $this->getEventsManager()->fire(new WorkerEvent\BeforeFork($this));

        $socket = null;
        $this->childPID = $this->fork($socket);

        if ($this->childPID !== 0) {
            $this->sockets[$this->childPID] = $socket;
            return;
        }

        if ($this->job instanceof BaseJob === false) {
            /**
             * @todo
             * Something went strange.
             * I definitely should sort out with this.
             */
            return;
        }

        $this->getEventsManager()->fire(new WorkerEvent\AfterFork($this));

        $this->processType = self::PROCESS_TYPE_JOB;

        $jid = $this->job->jid;
        $this->who = 'child:' . $this->name;
        $this->logContext = ['type' => $this->who];

        $this->title('Processing ' . $jid . ' since ' . strftime('%F %T'));
        $this->childPerform($this->job);

        socket_close($socket);

        exit(0);
    }

    /**
     * Process a single job.
     *
     * @param  BaseJob $job The job to be processed.
     * @return void
     */
    public function childPerform(BaseJob $job): void
    {
        $loggerContext = ['job' => $job->jid, 'type' => $this->who];

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

            $this->logger->notice('{type}: job {job} has finished', $loggerContext);
        } catch (\Throwable $e) {
            $loggerContext['stack'] = $e->getMessage();
            $this->logger->critical('{type}: job {job} has failed {stack}', $loggerContext);

            $job->fail(
                'system:fatal',
                sprintf('%s: %s in %s on line %d', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine())
            );
        }
    }

    /**
     * @param  int $status The status parameter supplied to a successful call to pcntl_* functions.
     * @return bool
     */
    private function childProcessStatus(int $status): bool
    {
        if ($this->childPID === null) {
            return false;
        }

        switch (true) {
            case pcntl_wifexited($status):
                $code = pcntl_wexitstatus($status);
                $res = $this->handleProcessExitStatus($this->childPID, self::PROCESS_TYPE_JOB, $code);

                if ($res !== false && $this->job instanceof BaseJob) {
                    $this->job->fail('system:fatal', $res);
                }
                return true;
            case pcntl_wifsignaled($status):
                $sig = pcntl_wtermsig($status);
                if ($sig !== SIGKILL) {
                    $this->childProcessUnhandledSignal($sig);
                }
                return true;
            case pcntl_wifstopped($status):
                $sig = pcntl_wstopsig($status);
                $this->logger->info(
                    sprintf(
                        "child %d was stopped with signal %s\n",
                        $this->childPID,
                        SignalHandler::sigName($sig)
                    )
                );
                return false;
            default:
                $this->logger->error(sprintf("unexpected status for child %d; exiting\n", $this->childPID));
                exit(1);
        }
    }

    private function childProcessUnhandledSignal($sig): void
    {
        $context = $this->logContext;
        $context['signal'] = SignalHandler::sigName($sig);
        $this->logger->notice("{type}: child terminated with unhandled signal '{signal}'", $context);
    }

    private function childKill(): void
    {
        if ($this->childPID === null) {
            return;
        }

        $this->logger->info('{type}: killing child at {child}', ['child' => $this->childPID, 'type' => $this->who]);

        if (pcntl_waitpid($this->childPID, $status, WNOHANG) !== -1) {
            posix_kill($this->childPID, SIGKILL);
        }

        $this->childPID = null;
    }

    private function watchdogStart(WatchdogSubscriber $subscriber): void
    {
        $this->getEventsManager()->fire(new WorkerEvent\BeforeFork($this));

        $socket = null;
        $this->watchdogPID = $this->fork($socket);

        if ($this->watchdogPID !== 0) {
            // Mater process should get out of here
            $this->sockets[$this->watchdogPID] = $socket;
            return;
        }

        if ($this->job instanceof BaseJob === false) {
            /**
             * @todo
             * Something went strange.
             * I definitely should sort out with this.
             */
            return;
        }

        $this->getEventsManager()->fire(new WorkerEvent\AfterFork($this));

        $this->processType = self::PROCESS_TYPE_WATCHDOG;

        $this->who = 'watchdog:' . $this->name;
        $this->logContext = ['type' => $this->who];

        $this->title(sprintf('Watching events for %s since %s', $this->job->jid, strftime('%F %T')));
        $subscriber->watchdog($this->job->jid, $this->name, $this->childPID);

        socket_close($socket);
        $this->logger->info("{type}: done", $this->logContext);

        exit(0);
    }

    /**
     * @param  int $status The status parameter supplied to a successful call to pcntl_* functions.
     * @return bool
     */
    private function watchdogProcessStatus(int $status): bool
    {
        if ($this->watchdogPID === null) {
            return false;
        }

        switch (true) {
            case pcntl_wifexited($status):
                $code = pcntl_wexitstatus($status);
                $this->handleProcessExitStatus($this->watchdogPID, self::PROCESS_TYPE_WATCHDOG, $code);
                return true;
            case pcntl_wifsignaled($status):
                $sig = pcntl_wtermsig($status);
                if ($sig !== SIGKILL) {
                    $this->logger->warning(
                        sprintf(
                            "watchdog %d terminated with unhandled signal %s\n",
                            $this->watchdogPID,
                            SignalHandler::sigName($sig)
                        )
                    );
                }
                return true;
            case pcntl_wifstopped($status):
                $sig = pcntl_wstopsig($status);
                $this->logger->warning(
                    sprintf(
                        "watchdog %d was stopped with signal %s\n",
                        $this->watchdogPID,
                        SignalHandler::sigName($sig)
                    )
                );
                return false;
            default:
                $this->logger->error(sprintf("unexpected status for watchdog %d; exiting\n", $this->childPID));
                exit(1);
        }
    }

    private function watchdogKill(): void
    {
        if ($this->watchdogPID) {
            $this->logger->info(
                '{type}: Killing watchdog at {child}',
                ['child' => $this->watchdogPID, 'type' => $this->who]
            );

            if (pcntl_waitpid($this->watchdogPID, $status, WNOHANG) !== -1) {
                posix_kill($this->watchdogPID, SIGKILL);
            }
            $this->watchdogPID = null;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function shutdown(): void
    {
        if ($this->childPID) {
            $message = '{type}: QUIT received; shutting down after child completes work';
        } else {
            $message = '{type}: QUIT received; shutting down';
        }

        $this->logger->notice($message, ['type' => $this->name]);
        $this->doShutdown();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function killChildren(): void
    {
        if (!$this->childPID && !$this->watchdogPID) {
            return;
        }

        $this->childKill();
        $this->watchdogKill();
    }

    /**
     * Stop when job count is exceeded
     *
     * @return void
     */
    private function stopWhenJobCountIsExceeded(): void
    {
        if ($this->isShuttingDown() || !($this->maximumNumberOfJobs > 0)) {
            return;
        }
        if (++$this->numberExecutedJobs >= $this->maximumNumberOfJobs) {
            $this->logger->info('Worker stopped due to maximum count of {count} exceeded', [
                'count' => $this->maximumNumberOfJobs
            ]);
            $this->shutdown();
        }
    }

    /**
     * Stop when time limit is reached
     *
     * @return void
     */
    private function stopWhenTimeLimitIsReached(): void
    {
        if ($this->isShuttingDown() || $this->timeLimitInSeconds === null) {
            return;
        }
        if ($this->endTime < microtime(true)) {
            $this->logger->info('Worker stopped due to time limit of {timeLimit}s reached', [
                'timeLimit' => $this->timeLimitInSeconds
            ]);
            $this->shutdown();
        }
    }

    /**
     * Stop when memory usage is exceeded
     *
     * @return void
     */
    private function stopWhenMemoryUsageIsExceeded(): void
    {
        if ($this->isShuttingDown() || $this->memoryLimit === null) {
            return;
        }
        if ($this->memoryLimit < memory_get_usage(true)) {
            $this->logger->info('Worker stopped due to memory limit of {limit} exceeded', [
                'limit' => $this->memoryLimit
            ]);
            $this->shutdown();
        }
    }
}

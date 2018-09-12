<?php

namespace Qless;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Events\Event;
use Qless\Events\Subscriber;
use Qless\Exceptions\ErrorCodes;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\RuntimeException;
use Qless\Jobs\Job;
use Qless\Jobs\JobHandlerInterface;

/**
 * Qless\Worker
 *
 * @package Qless
 */
class Worker
{
    private const PROCESS_TYPE_MASTER = 0;
    private const PROCESS_TYPE_JOB = 1;
    private const PROCESS_TYPE_WATCHDOG = 2;

    private $processType = self::PROCESS_TYPE_MASTER;

    /** @var Queue[] */
    private $queues = [];
    private $interval = 0;

    /** @var Client */
    private $client;

    private $shutdown = false;
    private $name;
    private $childPID = null;

    private $watchdogPID = null;

    private $childProcesses = 0;

    /** @var ?string */
    private $jobPerformClass = null;

    private $paused = false;

    private $who = 'master';
    private $logContext;

    /** @var resource[] */
    private $sockets = [];

    /** @var LoggerInterface */
    private $logger;

    /** @var Job|null */
    private $job;

    public function __construct(string $name, array $queues, Client $client, int $interval = 60)
    {
        $this->name = $name;
        $this->client = $client;
        $this->interval = $interval;

        foreach ($queues as $name) {
            $this->queues[] = new Queue($name, $client);
        }

        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Register Job perform handler.
     *
     * @param string $fqcl The fully qualified class name.
     * @return void
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function registerJobPerformHandler($fqcl)
    {
        if (!class_exists($fqcl)) {
            throw new RuntimeException(
                sprintf('Could not find job perform class %s.', $fqcl)
            );
        }

        $interfaces = class_implements($fqcl);
        if (in_array(JobHandlerInterface::class, $interfaces, true) == false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Provided Job class "%s" does not implement %s interface.',
                    $fqcl,
                    JobHandlerInterface::class
                )
            );
        }

        $this->jobPerformClass = $fqcl;
    }

    /**
     * starts worker
     */
    public function run()
    {
        declare(ticks=1);

        $this->startup();

        $this->who = 'master:' . $this->name;
        $this->logContext = [ 'type' => $this->who, 'job.identifier' => null ];
        $this->logger->info('{type}: Worker started', $this->logContext);
        $this->logger->info(
            '{type}: monitoring the following queues (in order), {queues}',
            ['type' => $this->who, 'queues' => implode(', ', $this->queues)]
        );

        $did_work = false;

        while (true) {
            if ($this->shutdown) {
                $this->logger->info('{type}: Shutting down', $this->logContext);
                break;
            }

            while ($this->paused) {
                usleep(250000);
            }

            if ($did_work) {
                $this->logger->debug('{type}: Looking for work', $this->logContext);
                $this->setProcessStatus(
                    'Waiting for ' . implode(',', $this->queues) . ' with interval ' . $this->interval
                );
                $did_work = false;
            }

            $job = $this->reserve();
            if ($job === null) {
                if ($this->interval == 0) {
                    break;
                }
                usleep($this->interval * 1000000);
                continue;
            }

            $this->job = $job;
            $this->logContext['job.identifier'] = $job->jid;

            // fork processes
            $this->childStart();
            $this->watchdogStart($this->client->createSubscriber(['ql:log']));

            // Parent process, sit and wait
            $proc_line = 'Forked ' . $this->childPID . ' at ' . strftime('%F %T');
            $this->setProcessStatus($proc_line);
            $this->logger->info($proc_line, $this->logContext);

            while ($this->childProcesses > 0) {
                $status = null;
                $pid   = pcntl_wait($status, WUNTRACED);
                if ($pid > 0) {
                    if ($pid === $this->childPID) {
                        $exited = $this->childProcessStatus($status);
                    } elseif ($pid === $this->watchdogPID) {
                        $exited = $this->watchdogProcessStatus($status);
                    } else {
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
            $this->job = null;
            $this->logContext['job.identifier'] = null;
            $did_work = true;

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
    }

    /** @return null|Job */
    public function reserve(): ?Job
    {
        foreach ($this->queues as $queue) {
            /** @var \Qless\Jobs\Job|null $job */
            $job = $queue->pop($this->name);
            if ($job !== null) {
                return $job;
            }
        }

        return null;
    }

    public function startup()
    {
        $this->masterRegisterSigHandlers();
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
    private function masterRegisterSigHandlers()
    {
        pcntl_signal(SIGTERM, [$this, 'shutDownNow'], false);
        pcntl_signal(SIGINT, [$this, 'shutDownNow'], false);
        pcntl_signal(SIGQUIT, [$this, 'shutdown'], false);
        pcntl_signal(SIGUSR1, [$this, 'killChildren'], false);
        pcntl_signal(SIGUSR2, [$this, 'pauseProcessing'], false);
        pcntl_signal(SIGCONT, [$this, 'unPauseProcessing'], false);

        $this->logger->info('Registered signals');
    }

    /**
     * Clear all previously registered signal handlers
     */
    private function clearSigHandlers()
    {
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT, SIG_DFL);
        pcntl_signal(SIGQUIT, SIG_DFL);
        pcntl_signal(SIGUSR1, SIG_DFL);
        pcntl_signal(SIGUSR2, SIG_DFL);
        pcntl_signal(SIGCONT, SIG_DFL);
    }

    /**
     * Forks and creates a socket pair for communication between parent and child process
     *
     * @param  resource $socket
     * @return int PID if master or 0 if child
     *
     * @throws RuntimeException
     */
    private function fork(&$socket)
    {
        $pair = [];
        if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair) === false) {
            $this->logger->error(
                '{type}: Unable to create socket pair; ' . socket_strerror(socket_last_error($pair[0])),
                $this->logContext
            );

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

        $reserved = str_repeat('x', 20240);

        register_shutdown_function(function () use (&$reserved, $socket) {
            // shutting down
            if (null === $error = error_get_last()) {
                return;
            }
            unset($reserved);

            if (call_user_func(new ErrorCodes, $error['type']) == null) {
                return;
            }

            $this->logger->debug('Sending error to master', $this->logContext);
            $data = serialize($error);

            do {
                $len = socket_write($socket, $data);
                if ($len === false || $len === 0) {
                    break;
                }

                $data = substr($data, $len);
            } while (is_numeric($len) && $len > 0);
        });

        return $pid;
    }

    /**
     * @param resource $socket
     *
     * @return null|string
     */
    private function readErrorFromSocket($socket)
    {
        $error_info = "";
        while (!empty($res = socket_read($socket, 8192))) {
            $error_info .= $res;
        }
        $error_info = unserialize($error_info);

        if (is_array($error_info)) {
            return sprintf(
                '[%s] %s:%d %s',
                call_user_func(new ErrorCodes, $error_info['type']) ?: 'Unknown',
                $error_info['file'],
                $error_info['line'],
                $error_info['message']
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
                $childType = 'child';
                break;
            default:
                $childType = 'watchdog';
        }

        if ($exitStatus === 0) {
            $this->logger->debug("{type}: {$childType} process exited successfully", $this->logContext);
            return false;
        }

        $error = $this->readErrorFromSocket($this->sockets[$pid]);
        $jobFailedMessage = $error ?: "{$childType} process failed with status: {$exitStatus}";

        $this->logger->error("{type}: fatal error in {$childType} process: {$jobFailedMessage}", $this->logContext);

        return $jobFailedMessage;
    }

    private function childStart(): void
    {
        $socket = null;
        $this->childPID = $this->fork($socket);
        if ($this->childPID !== 0) {
            $this->sockets[$this->childPID] = $socket;
            return;
        }

        if ($this->job instanceof Job == false) {
            return;
        }

        $this->processType = self::PROCESS_TYPE_JOB;
        $this->clearSigHandlers();

        $jid = $this->job->jid;
        $this->who = 'child:' . $this->name;
        $this->logContext = ['type' => $this->who];
        $status = 'Processing ' . $jid . ' since ' . strftime('%F %T');

        $this->setProcessStatus($status);
        $this->logger->info($status, $this->logContext);
        $this->childPerform($this->job);

        socket_close($socket);

        exit(0);
    }

    /**
     * Process a single job.
     *
     * @param  Job $job The job to be processed.
     * @return void
     */
    public function childPerform(Job $job): void
    {
        $context = ['job' => $job->jid, 'type' => $this->who];

        try {
            if ($this->jobPerformClass) {
                /** @var JobHandlerInterface $performClass */
                $performClass = new $this->jobPerformClass;
                $performClass->perform($job);
            } else {
                $job->perform();
            }
            $this->logger->notice('{type}: Job {job} has finished', $context);
        } catch (\Throwable $e) {
            $context['stack'] = $e->getMessage();

            $this->logger->critical('{type}: {job} has failed {stack}', $context);
            $job->fail('system:fatal', $e->getMessage());
        }
    }

    /**
     * @param  int $status The status parameter supplied to a successful call to pcntl_* functions.
     * @return bool
     */
    private function childProcessStatus(int $status): bool
    {
        switch (true) {
            case pcntl_wifexited($status):
                $code = pcntl_wexitstatus($status);
                $res = $this->handleProcessExitStatus($this->childPID, self::PROCESS_TYPE_JOB, $code);

                if ($res !== false && $this->job instanceof Job) {
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
                    sprintf("child %d was stopped with signal %s\n", $this->childPID, pcntl_sig_name($sig))
                );
                return false;
            default:
                $this->logger->error(sprintf("unexpected status for child %d; exiting\n", $this->childPID));
                exit(1);
        }
    }

    private function childProcessUnhandledSignal($sig)
    {
        $context = $this->logContext;
        $context['signal'] = pcntl_sig_name($sig);
        $this->logger->notice("{type}: child terminated with unhandled signal '{signal}'", $context);
    }

    private function childKill()
    {
        if ($this->childPID) {
            $this->logger->info('{type}: Killing child at {child}', ['child' => $this->childPID, 'type' => $this->who]);
            if (pcntl_waitpid($this->childPID, $status, WNOHANG) != -1) {
                posix_kill($this->childPID, SIGKILL);
            }
            $this->childPID = null;
        }
    }

    private function watchdogStart(Subscriber $subscriber): void
    {
        $socket = null;
        $this->watchdogPID = $this->fork($socket);
        if ($this->watchdogPID !== 0) {
            $this->sockets[$this->watchdogPID] = $socket;
            return;
        }

        if ($this->job instanceof Job == false) {
            return;
        }

        $this->processType = self::PROCESS_TYPE_WATCHDOG;
        $this->clearSigHandlers();

        $jid = $this->job->jid;
        $this->who = 'watchdog:' . $this->name;
        $this->logContext = ['type' => $this->who];
        $status = 'watching events for ' . $jid . ' since ' . strftime('%F %T');

        $this->setProcessStatus($status);
        $this->logger->info($status, $this->logContext);

        // @todo Move to a separated class
        ini_set('default_socket_timeout', -1);
        $subscriber->messages(function (string $channel, Event $event = null) use ($subscriber, $jid) {
            if ($event instanceof Event == false) {
                return;
            }

            if ($event->valid() == false || $event->getJid() !== $jid) {
                return;
            }

            switch ($event->getType()) {
                case Event::LOCK_LOST:
                    if ($event->getWorker() === $this->name) {
                        $this->logger->info(
                            "{type}: sending SIGKILL to child {$this->childPID}; job handed out to another worker",
                            $this->logContext
                        );
                        posix_kill($this->childPID, SIGKILL);
                        $subscriber->stop();
                    }
                    break;
                case Event::CANCELED:
                    if ($event->getWorker() === $this->name) {
                        $this->logger->info(
                            "{type}: sending SIGKILL to child {$this->childPID}; job canceled",
                            $this->logContext
                        );
                        posix_kill($this->childPID, SIGKILL);
                        $subscriber->stop();
                    }
                    break;
                case Event::COMPLETED:
                case Event::FAILED:
                    $subscriber->stop();
                    break;
            }
        });

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
                            pcntl_sig_name($sig)
                        )
                    );
                }
                return true;
            case pcntl_wifstopped($status):
                $sig = pcntl_wstopsig($status);
                $this->logger->warning(
                    sprintf("watchdog %d was stopped with signal %s\n", $this->watchdogPID, pcntl_sig_name($sig))
                );
                return false;
            default:
                $this->logger->error(sprintf("unexpected status for watchdog %d; exiting\n", $this->childPID));
                exit(1);
        }
    }

    private function watchdogKill()
    {
        if ($this->watchdogPID) {
            $this->logger->info(
                '{type}: Killing watchdog at {child}',
                ['child' => $this->watchdogPID, 'type' => $this->who]
            );

            if (pcntl_waitpid($this->watchdogPID, $status, WNOHANG) != -1) {
                posix_kill($this->watchdogPID, SIGKILL);
            }
            $this->watchdogPID = null;
        }
    }

    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing()
    {
        $this->logger->notice('{type}: USR2 received; pausing job processing', $this->logContext);
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing()
    {
        $this->logger->notice('{type}: CONT received; resuming job processing', $this->logContext);
        $this->paused = false;
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown()
    {
        if ($this->childPID) {
            $this->logger->notice('{type}: QUIT received; shutting down after child completes work', $this->logContext);
        } else {
            $this->logger->notice('{type}: QUIT received; shutting down', $this->logContext);
        }
        $this->doShutdown();
    }

    protected function doShutdown()
    {
        $this->shutdown = true;
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow()
    {
        $this->logger->notice('{type}: TERM or INT received; shutting down immediately', $this->logContext);
        $this->doShutdown();
        $this->killChildren();
    }

    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     */
    public function killChildren()
    {
        if (!$this->childPID && !$this->watchdogPID) {
            return;
        }

        $this->childKill();
        $this->watchdogKill();
    }

    /**
     * Sets the process status.
     *
     * @param  string $status
     * @return void
     */
    protected function setProcessStatus(string $status): void
    {
        cli_set_process_title('qless-php: ' . $status);
    }
}

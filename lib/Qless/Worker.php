<?php

namespace Qless;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class Worker
 *
 * @package Qless
 */
class Worker {

    /**
     * @var Queue[]
     */
    private $queues = array();
    private $interval = 0;
    /**
     * @var Client
     */
    private $client;

    private $shutdown = false;
    private $workerName;
    private $child = null;
    private $childSuspended = false;

    private $jobPerformClass = null;

    private $paused = false;

    private $who = 'master';
    private $logContext;

    /**
     * @var resource
     */
    private $socket;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Job
     */
    private $job;

    public function __construct($name, $queues, Client $client, $interval=60){
        $this->workerName = $name;
        $this->queues = [];
        $this->client = $client;
        $this->interval = $interval;
        foreach ($queues as $queue){
            $this->queues[] = $this->client->getQueue($queue);
        }
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    public function registerJobPerformHandler($klass){
        if(!class_exists($klass)) {
            throw new \Exception(
                'Could not find job perform class ' . $klass . '.'
            );
        }

        if(!method_exists($klass, 'perform')) {
            throw new \Exception(
                'Job class ' . $klass . ' does not contain perform method '
            );
        }

        $this->jobPerformClass = $klass;
    }

    static $SIGNALS = [
        // kill signals
        SIGKILL => 'SIGKILL',
        SIGTERM => 'SIGTERM',
        SIGINT  => 'SIGINT',
        SIGQUIT => 'SIGQUIT',

        // stop signals
        SIGSTOP => 'SIGSTOP',
        SIGTSTP => 'SIGTSTP',
    ];

    static $ERROR_CODES = [
        E_ERROR => 'E_ERROR',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_PARSE => 'E_PARSE',
        E_USER_ERROR => 'E_USER_ERROR',
    ];

    /**
     * starts worker
     */
    public function run() {

        declare(ticks=1);

        $this->startup();
        $this->who = 'master:' . $this->workerName;
        $this->logContext = [ 'type' => $this->who, 'job.identifier' => null ];
        $this->logger->info('{type}: Worker started', $this->logContext);
        $this->logger->info('{type}: monitoring the following queues (in order), {queues}', ['type'=>$this->who, 'queues' => implode(', ', $this->queues)]);

        $did_work = false;

        while (true){
            if ($this->shutdown){
                $this->logger->info('{type}: Shutting down', $this->logContext);
                break;
            }

            while ($this->paused) {
                usleep(250000);
            }

            if ($did_work) {
                $this->logger->debug('{type}: Looking for work', $this->logContext);
                $this->updateProcLine('Waiting for ' . implode(',', $this->queues) . ' with interval ' . $this->interval);
                $did_work = false;
            }

            $job = $this->reserve();
            if (!$job) {
                if ($this->interval == 0){
                    break;
                }
                usleep($this->interval * 1000000);
                continue;
            }

            $pair = [];
            if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $pair) === false) {
                $this->logger->error('{type}: Unable to create socket pair; ' . socket_strerror(socket_last_error($pair[0])), $this->logContext);
            }

            $this->logContext['job.identifier'] = $job->getId();
            $this->child = Qless::fork();

            // Forked and we're the child. Run the job.
            if ($this->child === 0) {
                /*** CHILD ***/
                $this->clearSigHandlers();

                socket_close($pair[0]);
                $this->socket = $pair[1];

                $reserved = str_repeat('x', 20240);

                register_shutdown_function(function() use (&$reserved) {
                    // shutting down
                    if (null === $error = error_get_last()) {
                        return;
                    }
                    unset($reserved);

                    $type = $error['type'];
                    if (!isset(self::$ERROR_CODES[$type])) {
                        return;
                    }

                    $this->logger->debug('Sending error to master', $this->logContext);
                    $data = serialize($error);

                    while (($len = socket_write($this->socket, $data)) > 0) {
                        $data = substr($data, $len);
                    }
                });

                $this->who = 'child:' . $this->workerName;
                $this->logContext = [ 'type' => $this->who ];
                $status = 'Processing ' . $job->getId() . ' since ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->info($status, $this->logContext);
                $this->perform($job);

                socket_close($this->socket);

                exit(0);
            }

            /*** PARENT ***/
            $this->socket = $pair[0];
            socket_close($pair[1]);
            socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 0, 'usec' => 10000]); // wait up to 10ms to receive data

            $this->job = $job;
            // Parent process, sit and wait
            $status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
            $this->updateProcLine($status);
            $this->logger->info($status, $this->logContext);

            while ($this->child) {
                usleep(250000);
            }

            socket_close($this->socket);
            $this->socket = null;
            $this->job = null;
            $this->logContext['job.identifier'] = null;
            $did_work = true;
        }
    }

    /**
     * @return bool|Job
     */
    public function reserve() {
        foreach($this->queues as $queue) {
            $job = $queue->pop($this->workerName);
            if ($job){
                return $job[0];
            }
        }
        return false;
    }

    /**
     * Process a single job.
     *
     * @param Job $job The job to be processed.
     */
    public function perform(Job $job)
    {
        try {
            if ($this->jobPerformClass) {
                $performClass = new $this->jobPerformClass;
                $performClass->perform($job);
            } else {
                $job->perform();
            }
            $this->logger->notice('{type}: Job {job} has finished', ['job' => $job->getId(), 'type' => $this->who]);
        }
        catch(\Exception $e) {
            $this->logger->critical('{type}: {job} has failed {stack}', ['job' => $job->getId(), 'stack' => $e->getMessage(), 'type' => $this->who]);
            $job->fail('system:fatal', $e->getMessage());
        }
    }

    public function startup(){
        $this->registerSigHandlers();
    }

    /**
     * Register signal handlers that a worker should respond to.
     *
     * TERM: Shutdown immediately and stop processing jobs.
     * INT: Shutdown immediately and stop processing jobs.
     * QUIT: Shutdown after the current job finishes processing.
     * USR1: Kill the forked child immediately and continue processing jobs.
     */
    private function registerSigHandlers()
    {
        pcntl_signal(SIGTERM, function() { $this->shutdownNow(); });
        pcntl_signal(SIGINT,  function() { $this->shutdownNow(); });
        pcntl_signal(SIGQUIT, function() { $this->shutdown(); });
        pcntl_signal(SIGUSR1, function() { $this->killChild(); });
        pcntl_signal(SIGUSR2, function() { $this->pauseProcessing(); });
        pcntl_signal(SIGCONT, function() { $this->unPauseProcessing(); });
        pcntl_signal(SIGCHLD, function() { $this->handleChild(); });
    }

    /**
     * called from the child
     */
    private function clearSigHandlers() {
        pcntl_signal(SIGTERM, SIG_DFL);
        pcntl_signal(SIGINT,  SIG_DFL);
        pcntl_signal(SIGQUIT, SIG_DFL);
        pcntl_signal(SIGUSR1, SIG_DFL);
        pcntl_signal(SIGUSR2, SIG_DFL);
        pcntl_signal(SIGCONT, SIG_DFL);
        pcntl_signal(SIGCHLD, SIG_DFL);
    }

    private function handleChild() {
        $res = pcntl_waitpid(0, $status, WNOHANG|WUNTRACED);
        if ($res === -1) return;

        $jobFailed = false;
        $jobFailedMessage = null;

        if (pcntl_wifstopped($status)) {
            $this->childSuspended = true;
            // child is still running
            $sig = pcntl_wstopsig($status);
            $this->logger->notice("{type}: child has been stopped; waiting for it to resume", $this->logContext);
        } else if (pcntl_wifsignaled($status)) {
            $this->child = null;
            $sig = pcntl_wtermsig($status);
            $context = $this->logContext;
            $context['signal'] = $sig;
            $this->logger->notice("{type}: child was terminated with signal {signal}", $context);
        } else if ($this->childSuspended) {
            // if the child was suspended by a SIGSTOP or SIGTSTP, then reaching this point means it was resumed
            $this->childSuspended = false;
            $this->logger->notice("{type}: child was resumed", $this->logContext);
        } else if (pcntl_wifexited($status)) {
            $exitStatus = pcntl_wexitstatus($status);
            if ($exitStatus === 0) {
                $this->logger->debug("{type}: Child completed successfully", $this->logContext);
            } else {

                $jobFailed = true;

                $error_info = unserialize(socket_read($this->socket, 8192));
                if (is_array($error_info)) {
                    $jobFailedMessage = sprintf('[%s] %s:%d %s', self::$ERROR_CODES[$error_info['type']], $error_info['file'], $error_info['line'], $error_info['message']);
                } else {
                    $jobFailedMessage = "child failed with status: " . $exitStatus;
                }
                $this->logger->error("{type}: fatal error in child: " . $jobFailedMessage, $this->logContext);
            }
            $this->child = null;
        }

        if ($this->child === null) {
            // workaround for a bug in php-redis issuing a QUIT command when the child terminates
            // which causes Redis to terminate the connection even though the parent process still
            // has a reference to the socket
            $this->client->reconnect();

            if ($jobFailed) {
                $this->job->fail('system:fatal', $jobFailedMessage);
            }
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
        if ($this->child) {
            $this->logger->notice('{type}: QUIT received; shutting down after child completes work', $this->logContext);
        } else {
            $this->logger->notice('{type}: QUIT received; shutting down', $this->logContext);
        }
        $this->doShutdown();
    }

    protected function doShutdown() {
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
        $this->killChild();
    }

    /**
     * Kill a forked child job immediately. The job it is processing will not
     * be completed.
     */
    public function killChild()
    {
        if(!$this->child) {
            $this->logger->debug('{type}: No child to kill.', $this->logContext);
            return;
        }

        $this->logger->info('{type}: Killing child at {child}', ['child' => $this->child, 'type'=>$this->who]);
        if (pcntl_waitpid($this->child, $status, WNOHANG) != -1) {
            $this->logger->debug('Child {child} found, killing.', ['child' => $this->child, 'type' => $this->who]);
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        } else {
            $this->logger->info('{type}: Child {child} not found, restarting.', ['child' => $this->child, 'type' => $this->who]);
            $this->doShutdown();
        }
    }

    protected function updateProcLine($status) {
        $processTitle = 'qless-' . Qless::VERSION . ': ' . $status;
        cli_set_process_title($processTitle);
    }

}
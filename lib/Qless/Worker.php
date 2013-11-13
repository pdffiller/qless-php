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

    private $jobPerformClass = null;

    private $paused = false;

    private $who = 'master';
    private $logContext;

    private $logger;

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

    /**
     * starts worker
     */
    public function run() {

        declare(ticks=1);

        $this->startup();
        $this->who = 'master';
        $this->logContext = [ 'type' => $this->who ];
        $this->logger->info('{type}: Worker started', $this->logContext);

        while (true){
            if ($this->shutdown){
                $this->logger->info('{type}: Shutting down', $this->logContext);
                break;
            }

            while ($this->paused) {
                usleep(250000);
            }

            // Attempt to find and reserve a job
            $this->logger->debug('{type}: Looking for work', $this->logContext);
            $this->updateProcLine('Waiting for ' . implode(',', $this->queues) . ' with interval ' . $this->interval);
            $job = $this->reserve();

            if (!$job) {
                if ($this->interval == 0){
                    break;
                }
                usleep($this->interval * 1000000);
                continue;
            }

            $this->child = Qless::fork();

            // Forked and we're the child. Run the job.
            if ($this->child === 0 || $this->child === false) {
                $this->who = 'child';
                $this->logContext = [ 'type' => $this->who ];
                $status = 'Processing ' . $job->getId() . ' since ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->info($status, $this->logContext);
                $this->perform($job);
                if ($this->child === 0) {
                    exit(0);
                }
            }

            if($this->child > 0) {
                // Parent process, sit and wait
                $status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
                $this->updateProcLine($status);
                $this->logger->info($status, $this->logContext);

                // Wait until the child process finishes before continuing
                while(($res = pcntl_waitpid(0, $status, WNOHANG)) === 0) {
                    usleep(250000);
                    // TODO: we will want check the job and if it's timed out and completed by another child, terminate this child
                };

                if ($res > 0) {
                    $exitStatus = pcntl_wexitstatus($status);
                    if ($exitStatus !== 0) {
                        $this->logger->debug("Child failed with status {$exitStatus}", $this->logContext);
                        $job->fail("child fail","child return: " . $exitStatus);
                    } else {
                        $this->logger->debug("{type}: Child completed successfully", $this->logContext);
                    }
                } else {
                    $this->logger->error("{type}: An error was returned by pcntl_wexitstatus waiting for child to terminate", $this->logContext);
                }

                // workaround for a but in php-redis issuing a QUIT command when the child terminates
                // which causes Redis to terminate the connection even though the parent process still
                // has a reference to the socket
                $this->client->reconnect();
            }
            $this->child = null;
        }
    }

    /**
     * @return bool|Job
     */
    public function reserve(){
        foreach($this->queues as $queue) {
            $job = $queue->pop($this->workerName);
            if ($job){
                return $job;
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
            if ($this->jobPerformClass){
                $performClass = new $this->jobPerformClass;
                $performClass->perform($job);
            } else {
                $job->perform();
            }
        }
        catch(\Exception $e) {
            $this->logger->critical('{type}: {job} has failed {stack}', ['job' => $job->getId(), 'stack' => $e->getMessage(), 'type' => $this->who]);
            $job->fail("exception", $e->getMessage());
            return;
        }

        $this->logger->notice('{type}: Job {job} has finished', ['job' => $job->getId(), 'type' => $this->who]);
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
        if(!function_exists('pcntl_signal')) {
            return;
        }

        pcntl_signal(SIGTERM, function() { $this->shutdownNow(); });
        pcntl_signal(SIGINT,  function() { $this->shutdownNow(); });
        pcntl_signal(SIGQUIT, function() { $this->shutdown(); });
        pcntl_signal(SIGUSR1, function() { $this->killChild(); });
        pcntl_signal(SIGUSR2, function() { $this->pauseProcessing(); });
        pcntl_signal(SIGCONT, function() { $this->unPauseProcessing(); });
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
        $this->shutdown = true;
        //$this->logger->log(Psr\Log\LogLevel::NOTICE, 'Shutting down');
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow()
    {
        $this->shutdown();
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
        if(exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            $this->logger->debug('Child {child} found, killing.', ['child' => $this->child, 'type' => $this->who]);
            posix_kill($this->child, SIGKILL);
            $this->child = null;
        }
        else {
            $this->logger->info('{type}: Child {child} not found, restarting.', ['child' => $this->child, 'type' => $this->who]);
            $this->shutdown();
        }
    }

    protected function updateProcLine($status) {
        $processTitle = 'qless-' . Qless::VERSION . ': ' . $status;
        cli_set_process_title($processTitle);
    }

}
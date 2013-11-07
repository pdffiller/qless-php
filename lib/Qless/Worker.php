<?php

namespace Qless;

/**
 * Class Worker
 *
 * @package Qless
 */
class Worker {

    private $queues = array();
    private $interval = 0;
    private $client;

    private $shutdown = false;
    private $workerName;
    private $child = 0;

    private $jobPerformClass = null;

    private $paused = false;

    public function __construct($name, $queues, $client, $interval=60){
        $this->workerName = $name;
        $this->queues = [];
        $this->client = $client;
        $this->interval = $interval;
        foreach ($queues as $queue){
            $this->queues[] = $this->client->getQueue($queue);
        }
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

        $this->startup();

        while (true){
            pcntl_signal_dispatch();
            if ($this->shutdown){
                echo "shutting down.";
                break;
            }
            echo "checking for some work. \n";

            // Attempt to find and reserve a job
            $job = false;
            if (!$this->paused){
                $job = $this->reserve();
            }
            else {
                echo "paused, skipping jobs.";
            }

            if (!$job){
                if ($this->interval == 0){
                    break;
                }
                usleep($this->interval * 1000000);
                continue;
            }

            $this->child = Qless::fork();
            //$this->child = 0;

            // Forked and we're the child. Run the job.
            if ($this->child === 0 || $this->child === false) {
                //$status = 'Processing ' . $job->queue . ' since ' . strftime('%F %T');
                //$this->updateProcLine($status);
                //$this->logger->log(Psr\Log\LogLevel::INFO, $status);
                echo "child job performing\n";
                $this->perform($job);
                if ($this->child === 0) {
                    echo "child job done, exiting. \n";
                    exit(0);
                }
            }

            if($this->child > 0) {
                // Parent process, sit and wait
                //$status = 'Forked ' . $this->child . ' at ' . strftime('%F %T');
                //$this->updateProcLine($status);
                //$this->logger->log(Psr\Log\LogLevel::INFO, $status);

                // Wait until the child process finishes before continuing
                pcntl_wait($status);
                $exitStatus = pcntl_wexitstatus($status);
                echo "continuing after child with child exit status: " . $exitStatus . "\n";
                if($exitStatus !== 0) {
                    // Q:  When is this not going to be 0?  If I kill it, it returns 0, other cases?
                    echo "child failed, marking as failed...\n";
                    $job->fail("child fail","child return: " . $exitStatus);
                }
            }

        }
    }

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
            }
            else{
                $job->perform();
            }
            //Resque_Event::trigger('afterFork', $job);
        }
        catch(\Exception $e) {
            //$this->logger->log(Psr\Log\LogLevel::CRITICAL, '{job} has failed {stack}', array('job' => $job, 'stack' => $e->getMessage()));
            $job->fail("exception", $e->getMessage());
            return;
        }

        //$job->updateStatus(Resque_Job_Status::STATUS_COMPLETE);
        //$this->logger->log(Psr\Log\LogLevel::NOTICE, '{job} has finished', array('job' => $job));
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

        declare(ticks = 1);
        pcntl_signal(SIGTERM, array($this, 'shutDownNow'));
        pcntl_signal(SIGINT, array($this, 'shutDownNow'));
        pcntl_signal(SIGQUIT, array($this, 'shutdown'));
        pcntl_signal(SIGUSR1, array($this, 'killChild'));
        pcntl_signal(SIGUSR2, array($this, 'pauseProcessing'));
        pcntl_signal(SIGCONT, array($this, 'unPauseProcessing'));
        //$this->logger->log(Psr\Log\LogLevel::DEBUG, 'Registered signals');
    }


    /**
     * Signal handler callback for USR2, pauses processing of new jobs.
     */
    public function pauseProcessing()
    {
        //$this->logger->log(Psr\Log\LogLevel::NOTICE, 'USR2 received; pausing job processing');
        echo "signal URS2, pausing processing";
        $this->paused = true;
    }

    /**
     * Signal handler callback for CONT, resumes worker allowing it to pick
     * up new jobs.
     */
    public function unPauseProcessing()
    {
        echo "signal CONT, unpause";
        //$this->logger->log(Psr\Log\LogLevel::NOTICE, 'CONT received; resuming job processing');
        $this->paused = false;
    }

    /**
     * Schedule a worker for shutdown. Will finish processing the current job
     * and when the timeout interval is reached, the worker will shut down.
     */
    public function shutdown()
    {
        echo "signal QUIT, shutdown.";
        $this->shutdown = true;
        //$this->logger->log(Psr\Log\LogLevel::NOTICE, 'Shutting down');
    }

    /**
     * Force an immediate shutdown of the worker, killing any child jobs
     * currently running.
     */
    public function shutdownNow()
    {
        echo "signal INT, shutdown NOW!";
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
            //$this->logger->log(Psr\Log\LogLevel::DEBUG, 'No child to kill.');
            return;
        }

        //$this->logger->log(Psr\Log\LogLevel::INFO, 'Killing child at {child}', array('child' => $this->child));
        if(exec('ps -o pid,state -p ' . $this->child, $output, $returnCode) && $returnCode != 1) {
            //$this->logger->log(Psr\Log\LogLevel::DEBUG, 'Child {child} found, killing.', array('child' => $this->child));
            posix_kill($this->child, SIGKILL);
            //$this->child = null;
        }
        else {
            //$this->logger->log(Psr\Log\LogLevel::INFO, 'Child {child} not found, restarting.', array('child' => $this->child));
            $this->shutdown();
        }
    }

}
<?php

namespace Qless\Demo;

use Qless\Job;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Qless\Demo\Worker
 *
 * @package Qless\Demo
 */
class Worker
{
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('APP');
        $this->logger->pushHandler(new StreamHandler(STDOUT, Logger::DEBUG));
    }

    public function myPerformMethod(Job $job): void
    {
        $this->logger->debug('We are in :', [__METHOD__]);

        $job->complete();

        // retry
        // heartbeat

        // fail
        // complete - on success, can throw in some cases, need to handle responses.

        // jobId = instance_id:job_name:user_id:additional if always unique
    }

    /**
     * Default perform method.
     *
     * @param  Job $job
     * @return void
     */
    public function perform(Job $job): void
    {
        $this->logger->debug('We are in :', [__METHOD__]);

        $job->complete();
    }

    public function myThrowMethod(Job $job): void
    {
        $this->logger->debug('We are in: ', [__METHOD__]);
        sleep(2);

        throw new \Exception('Sample job exception message.');
    }

    public function exitMethod(Job $job): void
    {
        $this->logger->debug('We are in :', [__METHOD__]);
        sleep(1);

        exit(1);
    }
}

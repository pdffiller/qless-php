<?php

namespace Qless\Demo;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Qless\Jobs\Job;
use Qless\Jobs\JobHandlerInterface;

/**
 * Qless\Demo\JobHandler
 *
 * @package Qless\Demo
 */
class JobHandler implements JobHandlerInterface
{
    /** @var Logger */
    private $logger;

    public function __construct()
    {
        $this->logger = new Logger('APP');
        $this->logger->pushHandler(new StreamHandler(STDOUT, Logger::DEBUG));
    }

    /**
     * {@inheritdoc}
     *
     * @param Job $job
     * @return void
     *
     * @throws \Exception
     */
    public function perform(Job $job): void
    {
        $this->logger->debug('JobHandler: The job data is: ', $job->data);

        $instance = $job->getInstance();
        $data = $job->data;
        $performMethod = $data['performMethod'];

        $instance->$performMethod($job);

        $this->logger->debug("JobHandler: Finished Job::{$performMethod}");
    }
}

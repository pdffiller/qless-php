<?php

namespace Qless\Jobs;

use Qless\Client;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;

/**
 * Qless\Jobs\AbstractJob
 *
 * The base class for both BaseJob and RecurringJob.
 *
 * @property-read string $jid
 *
 * @package Qless\Jobs
 */
abstract class AbstractJob implements EventsManagerAwareInterface, \ArrayAccess
{
    use EventsManagerAwareTrait;

    /**
     * The job id.
     *
     * @var string
     */
    protected $jid;

    /**
     * Internal Qless Client.
     *
     * @var Client
     */
    protected $client;

    /**
     * Job constructor.
     *
     * @param Client $client
     * @param string $jid
     */
    public function __construct(Client $client, string $jid)
    {
        $this->client = $client;
        $this->jid = $jid;
    }

    /**
     * Creates the instance to perform the job and calls the method on the instance.
     *
     * @return bool
     */
    abstract public function perform(): bool;
}

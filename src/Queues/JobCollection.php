<?php

namespace Qless\Queues;

use Qless\Client;
use Qless\Jobs\BaseJob;
use Qless\Jobs\RecurringJob;

/**
 * Qless\Queues\JobCollection
 *
 * @package Qless\Queues
 */
class JobCollection
{

    /**
     * @var string
     */
    protected $queueName;

    /** @var Client */
    private $client;

    /**
     * Collection constructor.
     *
     * @param string $queueName
     * @param Client $client
     */
    public function __construct(string $queueName, Client $client)
    {
        $this->queueName = $queueName;
        $this->client = $client;
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return BaseJob[]
     */
    public function depends(int $offset = 0, $count = 25): array
    {
        return $this->getJobs(__FUNCTION__, $offset, $count);
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return RecurringJob[]
     */
    public function recurring(int $offset = 0, $count = 25): array
    {
        return $this->getJobs(__FUNCTION__, $offset, $count, false);
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return BaseJob[]
     */
    public function running(int $offset = 0, $count = 25): array
    {
        return $this->getJobs(__FUNCTION__, $offset, $count);
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return BaseJob[]
     */
    public function stalled(int $offset = 0, $count = 25): array
    {
        return $this->getJobs(__FUNCTION__, $offset, $count);
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return BaseJob[]
     */
    public function scheduled(int $offset = 0, $count = 25): array
    {
        return $this->getJobs(__FUNCTION__, $offset, $count);
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return BaseJob[]
     */
    public function waiting(int $offset = 0, $count = 25): array
    {
        return $this->getJobs(__FUNCTION__, $offset, $count);
    }

    /**
     * @param string $state
     * @param int $offset
     * @param int $count
     *
     * @param bool $multiGet
     *
     * @return array
     */
    protected function getJobs(string $state, int $offset = 0, $count = 25, bool $multiGet = true): array
    {
        $jids = $this->client->jobs($state, $this->queueName, $offset, $count);

        if ($multiGet) {
            return $this->client->jobs->multiget($jids);
        }

        $res = [];

        foreach ($jids as $jid) {
            if (($job = $this->client->jobs[$jid]) !== null) {
                $res[$jid] = $job;
            }
        }

        return $res;
    }
}

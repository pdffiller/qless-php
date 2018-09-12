<?php

namespace  Qless\Jobs;

use ArrayAccess;
use Qless\Client;
use Qless\Exceptions\ExceptionInterface;
use Qless\Exceptions\UnsupportedFeatureException;

/**
 * Qless\Jobs\Collection
 *
 * @package Qless\Jobs
 */
class Collection implements ArrayAccess
{
    /** @var Client */
    private $client;

    /**
     * Jobs constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Return a paginated list of JIDs which are in a completed state
     *
     * @param int $offset
     * @param int $count
     *
     * @return string[]
     */
    public function completed(int $offset = 0, int $count = 25)
    {
        return $this->client->jobs('complete', $offset, $count);
    }

    /**
     * Return a {@see Job} instance for the specified job identifier or null if the job does not exist
     *
     * @param string $jid the job identifier to fetch
     *
     * @return Job|null
     *
     * @throws ExceptionInterface
     */
    public function get(string $jid)
    {
        return $this->offsetGet($jid);
    }

    /**
     * Returns an array of jobs for the specified job identifiers, keyed by job identifier
     *
     * @param string[] $jids
     *
     * @return Job[]
     */
    public function multiget(array $jids): array
    {
        if (empty($jids)) {
            return [];
        }


        $results = call_user_func_array([$this->client, 'multiget'], $jids);
        $jobs = json_decode($results, true);

        if (!is_array($jobs)) {
            return [];
        }

        $ret = [];
        foreach ($jobs as $data) {
            $job = new Job($this->client, $data);
            $ret[$job->jid] = $job;
        }

        return $ret;
    }

    /**
     * Fetches a report of failed jobs for the specified group
     *
     * @param string|bool $group
     * @param int         $start
     * @param int         $limit
     *
     * @return array
     */
    public function failedForGroup($group, int $start = 0, int $limit = 25): array
    {
        $results = json_decode($this->client->failed($group, $start, $limit), true);

        if (isset($results['jobs']) && !empty($results['jobs'])) {
            $results['jobs'] = $this->multiget($results['jobs']);
        }

        return is_array($results) ? $results : [];
    }

    /**
     * Fetches a report of failed jobs, where the key is the group and the value is the number of jobs
     *
     * @return array
     */
    public function failed(): array
    {
        $results = json_decode($this->client->failed(), true);

        return is_array($results) ? $results : [];
    }

    /**
     * {@inheritdoc}
     *
     * @return bool
     */
    public function offsetExists($jid)
    {
        return $this->client->get($jid) !== false;
    }

    /**
     * {@inheritdoc}
     *
     * @return Job|null
     *
     * @throws ExceptionInterface
     */
    public function offsetGet($jid)
    {
        $data = $this->client->get($jid);

        if ($data === false) {
            $data = $this->client->call('recur.get', $jid);
            if ($data === false) {
                return null;
            }
        }

        return new Job($this->client, json_decode($data, true));
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetSet($offset, $value)
    {
        throw new UnsupportedFeatureException('Setting a job is not supported using Jobs class');
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetUnset($offset)
    {
        throw new UnsupportedFeatureException('Deleting a job is not supported using Jobs class');
    }
}

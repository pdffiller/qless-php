<?php

namespace Qless\Jobs;

use ArrayAccess;
use Qless\Client;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\UnsupportedFeatureException;

/**
 * Qless\Jobs\Collection
 *
 * A class for interacting with jobs.
 * Not meant to be instantiated directly, it's accessed through {@see Client::$jobs}.
 *
 * @package Qless\Jobs
 */
class Collection implements ArrayAccess
{
    /** @var Client */
    private $client;

    /**
     * Collection constructor.
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
     * @param  int $offset
     * @param  int $count
     * @return string[]
     */
    public function completed(int $offset = 0, int $count = 25): array
    {
        return $this->client->jobs('complete', null, $offset, $count);
    }

    /**
     * Return either a {@see BaseJob} or a {@see RecurringJob} instance for the
     * specified job identifier. Otherwise return NULL if the job does not exist.
     *
     * @param  string $jid the job identifier to fetch
     * @return BaseJob|RecurringJob|null
     *
     * @throws QlessException
     */
    public function get(string $jid)
    {
        return $this->offsetGet($jid);
    }

    /**
     * Returns an array of jobs for the specified job identifiers, keyed by job identifier
     *
     * @param  string[] $jids
     * @return BaseJob[]
     */
    public function multiget(array $jids): array
    {
        if (empty($jids)) {
            return [];
        }


        $results = call_user_func_array([$this->client, 'multiget'], $jids);
        $jobs = json_decode($results, true) ?: [];

        $ret = [];
        foreach ($jobs as $data) {
            $job = new BaseJob($this->client, $data);
            $job->setEventsManager($this->client->getEventsManager());

            $ret[$job->jid] = $job;
        }

        return $ret;
    }

    /**
     * Fetches a report of failed jobs for the specified group
     *
     * @param string|bool $group
     * @param int $start
     * @param int $limit
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
     * Fetches a list of tagged job ids associated with provided tag.
     *
     * @param  string $tag
     * @param  int    $offset
     * @param  int    $limit
     * @return string[]
     */
    public function tagged(string $tag, int $offset = 0, int $limit = 25): array
    {
        $response = json_decode($this->client->call('tag', 'get', $tag, $offset, $limit), true);
        if (empty($response['jobs'])) {
            $response['jobs'] = [];
        }

        return $response['jobs'];
    }

    /**
     * Fetches a list of tracked jobs.
     *
     * @return array
     */
    public function tracked(): array
    {
        $tracked = $this->client->call('tracked') ?? '[]';

        return json_decode($tracked, true);
    }

    /**
     * Reads jobs in a worker.
     *
     * @param string $worker
     * @param string $subTimeInterval  specify last time interval, i.e. '2 hours', '15 mins' and etc; all jobs on empty
     * @return array  BaseJob[]
     */
    public function fromWorker(string $worker, string $subTimeInterval = ''): array
    {
        try {
            $now = new \DateTime();
            $interval = date_interval_create_from_date_string($subTimeInterval);
            $timestamp = $now->sub($interval)->getTimestamp();
        } catch (\Exception $e) {
            $timestamp = -1;
        }

        if ($subTimeInterval === '' || $timestamp === -1) {
            $jids = json_decode($this->client->workerJobs($worker), true) ?: [];
        } else {
            $jids = json_decode($this->client->workerJobs($worker, $timestamp), true) ?: [];
        }

        return $this->multiget($jids);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $jid
     * @return bool
     */
    public function offsetExists($jid): bool
    {
        return $this->client->get($jid) !== null;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $jid
     * @return BaseJob|RecurringJob|null
     *
     * @throws QlessException
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($jid)
    {
        $data = $this->client->get($jid);

        if (empty($data)) {
            $data = $this->client->call('recur.get', $jid);
            if (empty($data)) {
                return null;
            }

            $job = new RecurringJob($this->client, json_decode($data, true));
        } else {
            $job = new BaseJob($this->client, json_decode($data, true));
        }

        $job->setEventsManager($this->client->getEventsManager());

        return $job;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetSet($offset, $value): void
    {
        throw new UnsupportedFeatureException('Setting a job is not supported using Jobs collection.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetUnset($offset): void
    {
        throw new UnsupportedFeatureException('Deleting a job is not supported using Jobs collection.');
    }

    /**
     * @param int $offset
     * @param int $count
     *
     * @return array
     */
    public function tagsList(int $offset = 0, int $count = 10000): array
    {
        return json_decode($this->client->tags($offset, $count), true) ?: [];
    }
}

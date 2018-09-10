<?php

namespace Qless;

use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Ramsey\Uuid\Uuid;

/**
 * Qless\Queue
 *
 * @package Qless
 *
 * @property int $heartbeat get / set the heartbeat timeout for the queue
 */
class Queue
{
    /** @var Client */
    private $client;

    /** @var string */
    private $name;

    /**
     * Queue constructor.
     *
     * @param string $name
     * @param Client $client
     */
    public function __construct(string $name, Client $client)
    {
        $this->client = $client;
        $this->name   = $name;
    }

    /**
     * Put the described job in this queue.
     *
     * Either create a new job in the provided queue with the provided attributes,
     * or move that job into that queue. If the job is being serviced by a worker,
     * subsequent attempts by that worker to either `heartbeat` or `complete` the
     * job should fail and return `false`.
     *
     * The `priority` argument should be negative to be run sooner rather than
     * later, and positive if it's less important. The `tags` argument should be
     * a JSON array of the tags associated with the instance and the `valid after`
     * argument should be in how many seconds the instance should be considered
     * actionable.
     *
     * @param string $klass     The class with the 'performMethod' specified in the data.
     * @param string $jid       The specified job id, if false is specified, a jid will be generated.
     * @param mixed  $data      An array of parameters for job.
     * @param int    $delay     The specified delay to run job.
     * @param int    $retries   Number of retries allowed.
     * @param bool   $replace   False to prevent the job from being replaced if it is already running.
     * @param int    $priority  A greater priority will execute before jobs of lower priority.
     * @param array  $resources A list of resource identifiers this job must acquire before being processed.
     * @param float  $interval  The minimum number of seconds required to transpire before the next
     *                          instance of this job can run.
     * @param array  $tags
     * @param array  $depends   A list of JIDs this job must wait on before executing
     *
     * @return string|float The job identifier or the time remaining before the job expires
     *                      if the job is already running.
     *
     * @throws RuntimeException
     * @throws QlessException
     */
    public function put(
        $klass,
        $jid,
        $data,
        $delay = 0,
        $retries = 5,
        $replace = true,
        $priority = 0,
        $resources = [],
        $interval = 0.0,
        $tags = [],
        $depends = []
    ) {
        try {
            $jid = $jid ?: Uuid::uuid4();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->client->put(
            '',
            $this->name,
            $jid,
            $klass,
            json_encode($data, JSON_UNESCAPED_SLASHES),
            $delay,
            'priority',
            $priority,
            'tags',
            json_encode($tags, JSON_UNESCAPED_SLASHES),
            'retries',
            $retries,
            'depends',
            json_encode($depends, JSON_UNESCAPED_SLASHES),
            'resources',
            json_encode($resources, JSON_UNESCAPED_SLASHES),
            'replace',
            $replace ? 1 : 0,
            'interval',
            $interval
        );
    }

    /**
     * Get the next job on this queue.
     *
     * @param string $worker  Worker name popping the job.
     * @param int    $numJobs Number of jobs to pop off of the queue.
     *
     * @return Job[]
     *
     * @throws QlessException
     */
    public function pop(string $worker, int $numJobs = 1): array
    {
        $results = $this->client->pop($this->name, $worker, $numJobs);

        $returnJobs = [];
        if ($results && $jobs = json_decode($results, true)) {
            foreach ($jobs as $data) {
                $returnJobs[] = new Job($this->client, $data);
            }
        }

        return $returnJobs;
    }

    /**
     * Make a recurring job in this queue.
     *
     * The `priority` argument should be negative to be run sooner rather than
     * later, and positive if it's less important. The `tags` argument should be
     * a JSON array of the tags associated with the instance.
     *
     * @param string $klass     The class with the 'performMethod' specified in the data.
     * @param string $jid       The specified job id, if false is specified, a jid will be generated.
     * @param mixed  $data      An array of parameters for job.
     * @param int    $interval  The recurring interval in seconds.
     * @param int    $offset    A delay before the first run in seconds.
     * @param int    $retries   Number of times the job can retry when it runs.
     * @param int    $priority  A negative priority will run sooner.
     * @param array  $resources An array of resource identifiers this job must acquire before being processed.
     * @param array  $tags      An array of tags to add to the job.
     *
     * @return mixed
     *
     * @throws RuntimeException
     * @throws QlessException
     */
    public function recur(
        $klass,
        $jid,
        $data,
        $interval = 0,
        $offset = 0,
        $retries = 5,
        $priority = 0,
        $resources = [],
        $tags = []
    ) {
        try {
            $jid = $jid ?: Uuid::uuid4();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        return $this->client->recur(
            $this->name,
            $jid,
            $klass,
            json_encode($data, JSON_UNESCAPED_SLASHES),
            'interval',
            $interval,
            $offset,
            'priority',
            $priority,
            'tags',
            json_encode($tags, JSON_UNESCAPED_SLASHES),
            'retries',
            $retries,
            'resources',
            json_encode($resources, JSON_UNESCAPED_SLASHES)
        );
    }


    /**
     * Cancels a job using the specified identifier
     *
     * @param $jid
     *
     * @return int
     */
    public function cancel($jid)
    {
        return $this->client->cancel($jid);
    }

    /**
     * Remove a recurring job using the specified identifier
     *
     * @param $jid
     *
     * @return int
     */
    public function unrecur($jid)
    {
        return $this->client->unrecur($jid);
    }

    /**
     * Get the length of the queue.
     *
     * @return int
     */
    public function length()
    {
        return $this->client->length($this->name);
    }

    public function __get($name)
    {
        switch ($name) {
            case 'heartbeat':
                $cfg = $this->client->config;

                return intval($cfg->get("{$this->name}-heartbeat", $cfg->get('heartbeat', 60)));

            default:
                throw new \InvalidArgumentException("Undefined property '$name'");
        }
    }

    public function __set($name, $value)
    {
        switch ($name) {
            case 'heartbeat':
                if (!is_int($value)) {
                    throw new \InvalidArgumentException('heartbeat must be an int');
                }

                $this->client
                    ->config
                    ->set("{$this->name}-heartbeat", $value);

                break;

            default:
                throw new \InvalidArgumentException("Undefined property '$name'");
        }
    }

    public function __unset($name)
    {
        switch ($name) {
            case 'heartbeat':
                $this->client
                    ->config
                    ->clear("{$this->name}-heartbeat");

                break;

            default:
                throw new \InvalidArgumentException("Undefined property '$name'");
        }
    }

    /**
     * Retrieve stats from the queue
     *
     * @param int $date The date for which stats to retrieve as a unix timestamp
     *
     * @return array
     */
    public function stats($date = null)
    {
        $date = $date ? : time();

        return $this->client->stats($this->name, $date);
    }

    /**
     * Pauses the queue so it will not process any more jobs
     */
    public function pause()
    {
        $this->client->pause($this->name);
    }

    /**
     * Resumes the queue so it will continue processing jobs
     */
    public function resume()
    {
        $this->client->unpause($this->name);
    }

    /**
     * Specifies whether the queue is paused
     *
     * @return bool
     */
    public function isPaused()
    {
        return $this->client->paused($this->name) === 1;
    }

    public function __toString()
    {
        return $this->name;
    }
}

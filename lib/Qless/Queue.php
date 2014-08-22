<?php

namespace Qless;

require_once __DIR__ . '/Qless.php';
require_once __DIR__ . '/Job.php';

/**
 * @property int $heartbeat get / set the heartbeat timeout for the queue
 */
class Queue
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $name;

    public function __construct($name, Client $client) {
        $this->client = $client;
        $this->name   = $name;
    }

    /**
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
     * @param string $jid       specified job id, if false is specified, a jid will be generated.
     * @param mixed  $data      array of parameters for job.
     * @param int    $delay     specify delay to run job.
     * @param int    $retries   number of retries allowed.
     * @param bool   $replace   false to prevent the job from being replaced if it is already running
     * @param int    $priority  a greater priority will execute before jobs of lower priority
     * @param array  $resources a list of resource identifiers this job must acquire before being processed
     * @param float  $interval  the minimum number of seconds required to transpire before the next instance of this job can run
     * @param array  $tags
     * @param array  $depends   a list of JIDs this job must wait on before executing
     *
     * @return string|float the job identifier or the time remaining before the job expires if the job is already running
     */
    public function put($klass, $jid, $data, $delay = 0, $retries = 5, $replace = true, $priority = 0, $resources = [], $interval = 0.0, $tags = [], $depends = []) {
        return $this->client->put(null,
            $this->name,
            $jid ?: Qless::guidv4(),
            $klass,
            json_encode($data, JSON_UNESCAPED_SLASHES),
            $delay,
            'priority', $priority,
            'tags', json_encode($tags, JSON_UNESCAPED_SLASHES),
            'retries', $retries,
            'depends', json_encode($depends, JSON_UNESCAPED_SLASHES),
            'resources', json_encode($resources, JSON_UNESCAPED_SLASHES),
            'replace', $replace ? 1 : 0,
            'interval', $interval
        );
    }

    /**
     * Get the next job on this queue.
     *
     * @param     $worker - worker name popping the job.
     * @param int $numJobs - number of jobs to pop off of the queue
     *
     * @return Job[]
     */
    public function pop($worker, $numJobs = 1) {
        $results = $this->client
            ->pop($this->name, $worker, $numJobs);

        $jobs = json_decode($results, true);

        $returnJobs = [];
        if (!empty($jobs)) {
            foreach($jobs as $job_data) {
                $returnJobs[] = new Job($this->client, $job_data);
            }
        }

        return $returnJobs;
    }


    /**
     * Cancels a job using the specified identifier
     *
     * @param $jid
     *
     * @return int
     */
    public function cancel($jid) {
        return $this->client->cancel($jid);
    }

    /**
     * Get the length of the queue.
     *
     * @return int
     */
    public function length() {
        return $this->client->length($this->name);
    }

    function __get($name) {
        switch ($name) {
            case 'heartbeat':
                $cfg = $this->client->config;

                return intval($cfg->get("{$this->name}-heartbeat", $cfg->get('heartbeat', 60)));

            default:
                throw new \InvalidArgumentException("Undefined property '$name'");
        }
    }

    function __set($name, $value) {
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

    function __unset($name) {
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
    public function stats($date = null) {
        $date = $date ? : time();

        return $this->client->stats($this->name, $date);
    }

    /**
     * Pauses the queue so it will not process any more jobs
     */
    public function pause() {
        $this->client->pause($this->name);
    }

    /**
     * Resumes the queue so it will continue processing jobs
     */
    public function resume() {
        $this->client->unpause($this->name);
    }

    /**
     * Specifies whether the queue is paused
     *
     * @return bool
     */
    public function isPaused() {
        return $this->client->paused($this->name) === 1;
    }

    function __toString() {
        return $this->name;
    }


}
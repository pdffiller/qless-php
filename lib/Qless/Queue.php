<?php

namespace Qless;

require_once __DIR__ . '/Qless.php';
require_once __DIR__ . '/Job.php';


class Queue
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var Client
     */
    private $client;

    public function __construct($name, Client $client) {
        $this->name   = $name;
        $this->client = $client;
    }

    /**
     *  * Either create a new job in the provided queue with the provided attributes,
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
     * @param        $klass     - The class with the 'performMethod' specified in the data.
     * @param        $jid       - specified job id, if null, will be generated.
     * @param        $data      - array of parameters for job.
     * @param int    $priority
     * @param array  $tags
     * @param int    $delay     - specify delay to run job.
     * @param int    $retries   - number of retries allowed.
     * @param array  $depends
     * @param array  $resources a list of resource identifiers this job must acquire before being processed
     *
     * @return mixed
     */
    public function put($klass, $jid, $data, $priority = 0, $tags = [], $delay = 0, $retries = 5, $depends = [], $resources = []) {
        $useJID = empty($jid) ? Qless::guidv4() : $jid;

        return $this->client->put(null,
            $this->name,
            $useJID,
            $klass,
            json_encode($data, JSON_UNESCAPED_SLASHES),
            $delay,
            'priority', $priority,
            'tags', json_encode($tags, JSON_UNESCAPED_SLASHES),
            'retries', $retries,
            'depends', json_encode($depends, JSON_UNESCAPED_SLASHES),
            'resources', json_encode($resources, JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Get the next job on this queue.
     *
     * @param $worker - worker name popping the job.
     *
     * @return null|Job
     */
    public function pop($worker) {
        $results = $this->client
            ->pop($this->name, $worker, 1);

        $jobs = json_decode($results, true);

        $returnJob = null;
        if (!empty($jobs)) {
            $job       = $jobs[0];
            $returnJob = new Job($this->client, $job['jid'], $job['worker'], $job['klass'], $job['queue'], $job['state'], $job['data']);
        }

        return $returnJob;
    }

    /**
     * Get the length of the queue.
     *
     * @return int
     */
    public function length() {
        return $this->client->length($this->name);
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
} 
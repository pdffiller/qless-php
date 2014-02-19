<?php

namespace Qless;

require_once __DIR__ . '/QlessException.php';

class Job
{

    private $jid;
    private $data;
    /**
     * @var Client
     */
    private $client;
    private $queue_name;
    private $klass_name;
    /**
     * @var string
     */
    private $worker_name;
    private $instance;
    /**
     * @var float
     */
    private $expires;

    /**
     * @var array
     */
    private $job_data;

    public function __construct(Client $client, $job_data) {
        $this->client      = $client;
        $this->jid         = $job_data['jid'];
        $this->klass_name  = $job_data['klass'];
        $this->queue_name  = $job_data['queue'];
        $this->data        = json_decode($job_data['data'], true);
        $this->worker_name = $job_data['worker'];
        $this->expires     = $job_data['expires'];
        $this->job_data    = $job_data;
    }

    /**
     * @param Client $client
     * @param array  $job_data
     *
     * @return Job
     */
    public static function fromJobData(Client $client, $job_data) {
        return new Job($client, $job_data);
    }

    public function getId() {
        return $this->jid;
    }

    /**
     * Seconds remaining before this job will timeout
     *
     * @return float
     */
    public function ttl() {
        return $this->expires - microtime(true);
    }

    /**
     * Return the job data
     *
     * @return mixed
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Get the name of the queue this job is on.
     * @return mixed
     */
    public function getQueueName() {
        return $this->job_data['queue'];
    }

    /**
     * Returns a list of jobs which are dependent upon this one completing successfully
     *
     * @return string[]
     */
    public function getDependents() {
        return $this->job_data['dependents'];
    }

    /**
     * Returns a list of jobs which must complete successfully before this will be run
     *
     * @return string[]
     */
    public function getDependencies() {
        return $this->job_data['dependencies'];
    }

    /**
     * Gets the number of retries remaining for this job
     *
     * @return int
     */
    public function getRetriesLeft() {
        return $this->job_data['remaining'];
    }

    /**
     * Gets the number of retries originally requested
     *
     * @return int
     */
    public function getOriginalRetries() {
        return $this->job_data['retries'];
    }

    /**
     * Returns the name of the worker currently performing the work or empty
     *
     * @return string
     */
    public function getWorkerName() {
        return $this->job_data['worker'];
    }

    /**
     * Get the job history
     *
     * @return array
     */
    public function getHistory() {
        return $this->job_data['history'];
    }

    /**
     * Return the current state of the job
     *
     * @return string
     */
    public function getState() {
        return $this->job_data['state'];
    }

    /**
     * Get the list of tags associated with this job
     *
     * @return string[]
     */
    public function getTags() {
        return $this->job_data['tags'];
    }

    /**
     * Returns the failure information for this job
     *
     * @return array
     */
    public function getFailureInfo() {
        return $this->job_data['failure'];
    }

    /**
     * Change the status of this job to complete
     *
     * @return bool
     */
    public function complete() {
        $jsonData = json_encode($this->data, JSON_UNESCAPED_SLASHES);
        return $this->client
            ->complete($this->jid,
                $this->worker_name,
                $this->queue_name,
                $jsonData
            );

    }

    /**
     * Return the job to the work queue for processing
     *
     * @param string $group
     * @param string $message
     * @param int $delay
     *
     * @return int remaining retries available
     */
    public function retry($group, $message, $delay = 0) {
        return $this->client
            ->retry($this->jid,
                $this->queue_name,
                $this->worker_name,
                $delay,
                $group,
                $message
            );
    }

    /**
     * @param bool|null $data
     *
     * @throws QlessException If the heartbeat fails
     * @return int timestamp of the heartbeat
     */
    public function heartbeat($data = null) {
        // (now, jid, worker, data)
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        }

        return $this->expires = $this->client
            ->heartbeat($this->jid, $this->worker_name, $data);
    }

    /**
     * Cancels the specified job and optionally all it's dependents
     *
     * @param bool $dependents true if associated dependents should also be cancelled
     *
     * @return int
     */
    public function cancel($dependents=false) {
        if ($dependents && !empty($this->job_data['dependents'])) {
            return call_user_func_array([$this->client, 'cancel'], array_merge([$this->jid], $this->job_data['dependents']));
        }
        return $this->client->cancel($this->jid);
    }

    /**
     * Creates the instance to perform the job and calls the method on the Instance specified in the payload['performMethod'];
     * @return bool
     */
    public function perform() {

        try {
            $instance = $this->getInstance();

            $performMethod = $this->data['performMethod'];

            $instance->$performMethod($this);

        } catch (\Exception $e) {
            $this->fail('system:fatal', $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param $group
     * @param $message
     *
     * @return bool
     * return values -
     */
    public function fail($group, $message) {
        $jsonData = json_encode($this->data, JSON_UNESCAPED_SLASHES);

        return $this->client
            ->fail($this->jid, $this->worker_name, $group, $message, $jsonData);
    }

    /**
     * Timeout this job
     */
    public function timeout() {
        $this->client->timeout($this->jid);
    }

    /**
     * Get the instance of the class specified on this job.  This instance will
     * be used to call the payload['performMethod']
     * @return mixed
     * @throws \Exception
     */
    public function getInstance() {
        if (!is_null($this->instance)) {
            return $this->instance;
        }

        if (!class_exists($this->klass_name)) {
            throw new \Exception(
                'Could not find job class ' . $this->klass_name . '.'
            );
        }

        if (!method_exists($this->klass_name, $this->data['performMethod'])) {
            throw new \Exception(
                'Job class ' . $this->klass_name . ' does not contain perform method ' . $this->data['performMethod']
            );
        }

        $this->instance = new $this->klass_name;

        return $this->instance;
    }

} 
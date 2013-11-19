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
    private $state;
    /**
     * @var string
     */
    private $worker_name;
    private $instance;
    /**
     * @var float
     */
    private $expires;

    public function __construct(Client $client, $jid, $worker_name, $klass_name, $queue_name, $state, $data, $expires) {
        $this->jid         = $jid;
        $this->client      = $client;
        $this->klass_name  = $klass_name;
        $this->queue_name  = $queue_name;
        $this->state       = $state;
        $this->data        = json_decode($data, true);
        $this->worker_name = $worker_name;
        $this->expires     = $expires;
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
        return $this->queue_name;
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
     * Cancels the specified job, removing it from the queue
     *
     * @return int
     */
    public function cancel() {
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
            $this->fail("job exception", $e->getMessage());

            return false;
        }

        return true;
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

} 
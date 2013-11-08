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
    private $worker_name;
    private $instance;

    public function __construct(Client $client, $jid, $worker_name, $klass_name, $queue_name, $state, $data) {
        $this->jid         = $jid;
        $this->client      = $client;
        $this->klass_name  = $klass_name;
        $this->queue_name  = $queue_name;
        $this->state       = $state;
        $this->data        = json_decode($data, true);
        $this->worker_name = $worker_name;
    }

    public function getId() {
        return $this->jid;
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
     * @throws QlessException
     * @return bool
     */
    public function heartbeat($data = null) {
        // (now, jid, worker, data)
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        }

        return $this->client
            ->heartbeat($this->jid, $this->worker_name, $data);
    }

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
//            if(method_exists($instance, 'setUp')) {
//                $instance->setUp();
//            }

            $performMethod = $this->data['performMethod'];

            $instance->$performMethod($this);

//            if(method_exists($instance, 'tearDown')) {
//                $instance->tearDown();
//            }
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

        //$this->instance->job = $this;
        //$this->instance->args = $this->getArguments();
        //$this->instance->queue = $this->queue;
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
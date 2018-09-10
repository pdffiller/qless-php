<?php

namespace Qless;

use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;

/**
 * Qless\Job
 *
 * @package Qless
 */
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
     * @var string[]
     */
    private $tags;

    /**
     * @var array
     */
    private $job_data;

    private $priority;

    public function __construct(Client $client, array $data)
    {
        $this->client      = $client;
        $this->jid         = $data['jid'];
        $this->klass_name  = $data['klass'];
        $this->queue_name  = $data['queue'];
        $this->data        = json_decode($data['data'], true);
        $this->worker_name = $data['worker'];
        $this->expires     = $data['expires'];
        $this->priority    = $data['priority'];
        $this->tags        = $data['tags'];
        $this->job_data    = $data;
    }

    /**
     * @param Client $client
     * @param array  $job_data
     *
     * @return Job
     */
    public static function fromJobData(Client $client, $job_data)
    {
        return new Job($client, $job_data);
    }

    public function getId()
    {
        return $this->jid;
    }

    /**
     * Seconds remaining before this job will timeout
     *
     * @return float
     */
    public function ttl()
    {
        return $this->expires - microtime(true);
    }

    /**
     * Return the job data
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the name of the queue this job is on.
     * @return mixed
     */
    public function getQueueName()
    {
        return $this->job_data['queue'];
    }

    /**
     * Returns a list of jobs which are dependent upon this one completing successfully
     *
     * @return string[]
     */
    public function getDependents()
    {
        return $this->job_data['dependents'];
    }

    /**
     * Returns a list of jobs which must complete successfully before this will be run
     *
     * @return string[]
     */
    public function getDependencies()
    {
        return $this->job_data['dependencies'];
    }

    /**
     * Returns a list of requires resources before this job can be processed
     *
     * @return string[]
     */
    public function getResources()
    {
        return $this->job_data['resources'];
    }

    /**
     * Returns the throttle interval for this job
     *
     * @return float
     */
    public function getInterval()
    {
        return floatval($this->job_data['interval']);
    }

    /**
     * Get the priority of this job
     *
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Gets the number of retries remaining for this job
     *
     * @return int
     */
    public function getRetriesLeft()
    {
        return $this->job_data['remaining'];
    }

    /**
     * Gets the number of retries originally requested
     *
     * @return int
     */
    public function getOriginalRetries()
    {
        return $this->job_data['retries'];
    }

    /**
     * Returns the name of the worker currently performing the work or empty
     *
     * @return string
     */
    public function getWorkerName()
    {
        return $this->job_data['worker'];
    }

    /**
     * Get the job history
     *
     * @return array
     */
    public function getHistory()
    {
        return $this->job_data['history'];
    }

    /**
     * Return the current state of the job
     *
     * @return string
     */
    public function getState()
    {
        return $this->job_data['state'];
    }

    /**
     * Get the list of tags associated with this job
     *
     * @todo This may return a string
     * @return string[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * Add the specified tags to this job.
     *
     * @param  string ...$tags A list of tags to remove from this job.
     * @return void
     */
    public function tag(...$tags)
    {
        $tags = func_get_args();
        $response = call_user_func_array([$this->client, 'call'], array_merge(['tag', 'add', $this->jid], $tags));

        $this->tags = json_decode($response, true);
    }

    /**
     * Remove the specified tags to this job
     *
     * @param string $tags... list of tags to add to this job
     */
    public function untag($tags)
    {
        $tags = func_get_args();
        $this->tags = json_decode(
            call_user_func_array([$this->client, 'call'], array_merge(['tag', 'remove', $this->jid], $tags)),
            true
        );
    }

    /**
     * Returns the failure information for this job
     *
     * @return array
     */
    public function getFailureInfo()
    {
        return $this->job_data['failure'];
    }

    /**
     * Change the status of this job to complete
     *
     * @return bool
     */
    public function complete()
    {
        $jsonData = json_encode($this->data, JSON_UNESCAPED_SLASHES);
        return $this->client
            ->complete(
                $this->jid,
                $this->worker_name,
                $this->queue_name,
                $jsonData
            );
    }

    /**
     * Options:
     *
     * optional values to replace when re-queuing job
     *
     * * int delay          delay (in seconds)
     * * array data         replacement data
     * * int priority       replacement priority
     * * int retries        replacement number of retries
     * * string[] tags      replacement tags
     * * string[] depends   replacement list of JIDs this job is dependent on
     * * string[] resources replacement list of resource IDs required before this job can be processed
     *
     * @param array $opts optional values
     * @return string
     */
    public function requeue($opts = [])
    {
        $opts = array_merge(
            [
                'delay'     => 0,
                'data'      => $this->data,
                'priority'  => $this->priority,
                'retries'   => $this->getOriginalRetries(),
                'tags'      => $this->getTags(),
                'depends'   => $this->getDependencies(),
                'resources' => $this->getResources(),
                'interval'  => $this->getInterval()
            ],
            $opts
        );

        return $this->client
            ->requeue(
                $this->worker_name,
                $this->queue_name,
                $this->jid,
                $this->klass_name,
                json_encode($opts['data'], JSON_UNESCAPED_SLASHES),
                $opts['delay'],
                'priority',
                $opts['priority'],
                'tags',
                json_encode($opts['tags'], JSON_UNESCAPED_SLASHES),
                'retries',
                $opts['retries'],
                'depends',
                json_encode($opts['depends'], JSON_UNESCAPED_SLASHES),
                'resources',
                json_encode($opts['resources'], JSON_UNESCAPED_SLASHES),
                'interval',
                floatval($opts['interval'])
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
    public function retry($group, $message, $delay = 0)
    {
        return $this->client
            ->retry(
                $this->jid,
                $this->queue_name,
                $this->worker_name,
                $delay,
                $group,
                $message
            );
    }

    /**
     * Set the timestamp of the new heartbeat.
     *
     * @param  array|null $data
     * @return int
     *
     * @throws QlessException If the heartbeat fails
     */
    public function heartbeat(array $data = null)
    {
        if (is_array($data)) {
            $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        }

        $this->expires = $this->client->heartbeat($this->jid, $this->worker_name, $data);

        return $this->expires;
    }

    /**
     * Cancels the specified job and optionally all it's dependents
     *
     * @param bool $dependents true if associated dependents should also be cancelled
     *
     * @return int
     */
    public function cancel($dependents = false)
    {
        if ($dependents && !empty($this->job_data['dependents'])) {
            return call_user_func_array(
                [$this->client, 'cancel'],
                array_merge([$this->jid], $this->job_data['dependents'])
            );
        }
        return $this->client->cancel($this->jid);
    }

    /**
     * Creates the instance to perform the job and calls the method on the instance.
     *
     * The instance must be specified in the payload['performMethod'];
     *
     * @return bool
     */
    public function perform()
    {
        try {
            $instance = $this->getInstance();

            $performMethod = $this->getPerformMethod();

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
     */
    public function fail($group, $message)
    {
        $jsonData = json_encode($this->data, JSON_UNESCAPED_SLASHES);

        return $this->client->fail($this->jid, $this->worker_name, $group, $message, $jsonData);
    }

    /**
     * Timeout this job
     *
     * @return void
     */
    public function timeout()
    {
        $this->client->timeout($this->jid);
    }

    /**
     * Get the instance of the class specified on this job.  This instance will
     * be used to call the payload['performMethod'] (or "perform" if not specified)
     *
     * @return object
     *
     * @throws RuntimeException
     */
    public function getInstance()
    {
        if ($this->instance !== null) {
            return $this->instance;
        }

        if (!class_exists($this->klass_name)) {
            throw new RuntimeException("Could not find job class {$this->klass_name}.");
        }

        $performMethod = $this->getPerformMethod();

        if (!method_exists($this->klass_name, $performMethod)) {
            throw new RuntimeException(
                sprintf(
                    'Job class "%s" does not contain perform method "%s".',
                    $this->klass_name,
                    $performMethod
                )
            );
        }

        $this->instance = new $this->klass_name;

        return $this->instance;
    }

    /**
     * Gets method to execute on the instance (defaults to "perform").
     *
     * @return string
     */
    protected function getPerformMethod(): string
    {
        if (is_array($this->data) && array_key_exists('performMethod', $this->data)) {
            return $this->data['performMethod'];
        }

        return 'perform';
    }
}

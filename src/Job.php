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
    /** @var string */
    private $jid;

    /** @var array */
    private $data;

    /** @var Client */
    private $client;

    /** @var string */
    private $queueName;

    /** @var string */
    private $className;

    /** @var string */
    private $workerName;

    /** @var ?object */
    private $instance;

    /** @var float */
    private $expires;

    /** @var string[] */
    private $tags;

    /** @var array */
    private $jobData;

    /** @var int */
    private $priority;

    /**
     * Job constructor.
     *
     * @param Client $client
     * @param array $data
     */
    public function __construct(Client $client, array $data)
    {
        $this->client = $client;
        $this->jobData = $data;

        $this->jid = $data['jid'];
        $this->className = $data['klass'];
        $this->queueName = $data['queue'];
        $this->data = json_decode($data['data'], true) ?: [];
        $this->workerName = $data['worker'];
        $this->expires = $data['expires'];
        $this->priority = $data['priority'];
        $this->tags = $data['tags'];
    }

    /**
     * Gets Job's ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->jid;
    }

    /**
     * Seconds remaining before this job will timeout.
     *
     * @return float
     */
    public function ttl(): float
    {
        return $this->expires - microtime(true);
    }

    /**
     * Return the job data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the name of the queue this job is on.
     *
     * @return string
     */
    public function getQueueName()
    {
        return $this->queueName;
    }

    /**
     * Returns a list of jobs which are dependent upon this one completing successfully
     *
     * @return string[]
     */
    public function getDependents()
    {
        return $this->jobData['dependents'];
    }

    /**
     * Returns a list of jobs which must complete successfully before this will be run
     *
     * @return string[]
     */
    public function getDependencies()
    {
        return $this->jobData['dependencies'];
    }

    /**
     * Returns a list of requires resources before this job can be processed
     *
     * @return string[]
     */
    public function getResources()
    {
        return $this->jobData['resources'];
    }

    /**
     * Returns the throttle interval for this job
     *
     * @return float
     */
    public function getInterval()
    {
        return floatval($this->jobData['interval']);
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
        return $this->jobData['remaining'];
    }

    /**
     * Gets the number of retries originally requested
     *
     * @return int
     */
    public function getOriginalRetries()
    {
        return $this->jobData['retries'];
    }

    /**
     * Returns the name of the worker currently performing the work or empty
     *
     * @return string
     */
    public function getWorkerName()
    {
        return $this->jobData['worker'];
    }

    /**
     * Get the job history
     *
     * @return array
     */
    public function getHistory()
    {
        return $this->jobData['history'];
    }

    /**
     * Return the current state of the job
     *
     * @return string
     */
    public function getState()
    {
        return $this->jobData['state'];
    }

    /**
     * Get the list of tags associated with this job
     *
     * FIXME: This may return a string
     *
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
        return $this->jobData['failure'];
    }

    /**
     * Complete a job and optionally put it in another queue,
     * either scheduled or to be considered waiting immediately.
     *
     * It can also optionally accept other jids on which this job will be considered
     * dependent before it's considered valid.
     *
     * @return string
     */
    public function complete(): string
    {
        $jsonData = json_encode($this->data, JSON_UNESCAPED_SLASHES) ?: '{}';

        return $this->client
            ->complete(
                $this->jid,
                $this->workerName,
                $this->queueName,
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

        $data = json_encode($opts['data'], JSON_UNESCAPED_SLASHES) ?: '{}';

        return $this->client
            ->requeue(
                $this->workerName,
                $this->queueName,
                $this->jid,
                $this->className,
                $data,
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
                $this->queueName,
                $this->workerName,
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

        $this->expires = $this->client->heartbeat($this->jid, $this->workerName, $data);

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
        if ($dependents && !empty($this->jobData['dependents'])) {
            return call_user_func_array(
                [$this->client, 'cancel'],
                array_merge([$this->jid], $this->jobData['dependents'])
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
    public function perform(): bool
    {
        try {
            $instance = $this->getInstance();

            $performMethod = $this->getPerformMethod();

            $instance->$performMethod($this);
        } catch (\Throwable $e) {
            $this->fail('system:fatal', $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Mark the current Job as failed, with the provided group, and a more specific message.
     *
     * @param string $group   Some phrase that might be one of several categorical modes of failure
     * @param string $message Something more job-specific, like perhaps a traceback.
     *
     * @return bool|string The id of the failed Job if successful, or FALSE on failure.
     */
    public function fail(string $group, string $message)
    {
        $jsonData = json_encode($this->data, JSON_UNESCAPED_SLASHES) ?: '{}';

        return $this->client->fail($this->jid, $this->workerName, $group, $message, $jsonData);
    }

    /**
     * Timeout this Job.
     *
     * @return void
     */
    public function timeout(): void
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

        if (!class_exists($this->className)) {
            throw new RuntimeException("Could not find job class {$this->className}.");
        }

        $performMethod = $this->getPerformMethod();

        if (!method_exists($this->className, $performMethod)) {
            throw new RuntimeException(
                sprintf(
                    'Job class "%s" does not contain perform method "%s".',
                    $this->className,
                    $performMethod
                )
            );
        }

        $this->instance = new $this->className;

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

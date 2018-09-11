<?php

namespace Qless;

use Qless\Exceptions\ExceptionInterface;
use Qless\Exceptions\InvalidArgumentException;
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
     * @param string      $className The class with the 'performMethod' specified in the data.
     * @param array       $data      An array of parameters for job.
     * @param string|null $jid       The specified job id, if not a specified, a jid will be generated.
     * @param int         $delay     The specified delay to run job.
     * @param int         $retries   Number of retries allowed.
     * @param bool        $replace   False to prevent the job from being replaced if it is already running.
     * @param int         $priority  A greater priority will execute before jobs of lower priority.
     * @param array       $resources A list of resource identifiers this job must acquire before being processed.
     * @param float       $interval  The minimum number of seconds required to transpire before the next
     *                               instance of this job can run.
     * @param array       $tags
     * @param array       $depends   A list of JIDs this job must wait on before executing
     *
     * @return string|float The job identifier or the time remaining before the job expires
     *                      if the job is already running.
     *
     * @throws ExceptionInterface
     * @throws RuntimeException
     */
    public function put(
        string $className,
        array $data,
        ?string $jid = null,
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
            $jid = $jid ?: Uuid::uuid4()->toString();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), null, $e->getCode(), $e);
        }

        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (empty($data)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to encode payload to put the described job "%s" to the "%s" queue.',
                    $jid,
                    $this->name
                )
            );
        }

        return $this->client->put(
            '',
            $this->name,
            $jid,
            $className,
            $data,
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
     * @throws ExceptionInterface
     * @throws RuntimeException
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
            $jid = $jid ?: Uuid::uuid4()->toString();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), null, $e->getCode(), $e);
        }

        $data = json_encode($data, JSON_UNESCAPED_SLASHES);
        if (empty($data)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to encode payload to make a recurring job "%s" for the "%s" queue.',
                    $jid,
                    $this->name
                )
            );
        }

        return $this->client->recur(
            $this->name,
            $jid,
            $klass,
            $data,
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
     * Cancels a job using the specified identifier.
     *
     * @param string $jid
     *
     * @return int
     */
    public function cancel(string $jid)
    {
        return $this->client->cancel($jid);
    }

    /**
     * Remove a recurring job using the specified identifier.
     *
     * @param string $jid
     *
     * @return int
     */
    public function unrecur(string $jid)
    {
        return $this->client->unrecur($jid);
    }

    /**
     * Get the length of the queue.
     *
     * @return int
     */
    public function length(): int
    {
        return $this->client->length($this->name);
    }

    /**
     * @param  string $name
     * @return int
     *
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     */
    public function __get($name)
    {
        switch ($name) {
            case 'heartbeat':
                $fallback = $this->client->config->get('heartbeat', 60);

                return (int) $this->client->config->get("{$this->name}-heartbeat", $fallback);
            default:
                throw new InvalidArgumentException("Undefined property '$name'");
        }
    }

    /**
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     */
    public function __set(string $name, $value): void
    {
        switch ($name) {
            case 'heartbeat':
                if (!is_int($value)) {
                    throw new InvalidArgumentException('heartbeat must be an int');
                }

                $this->client
                    ->config
                    ->set("{$this->name}-heartbeat", $value);
                break;
            default:
                throw new InvalidArgumentException("Undefined property '{$name}'");
        }
    }

    /**
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        switch ($name) {
            case 'heartbeat':
                try {
                    $this->client
                        ->config
                        ->clear("{$this->name}-heartbeat");
                } catch (\Throwable $e) {
                    // The unset shouldn't throw any exception. Thus do nothing
                }
                break;
            default:
                // The unset shouldn't throw any exception. Thus do nothing
        }
    }

    /**
     * Return the current statistics for a given queue on a given date.
     *
     * The results are returned are a JSON blob:
     * <code>
     * {
     *     # These are unimplemented as of yet
     *     'failed': 3,
     *     'retries': 5,
     *     'failures': 5,
     *     'wait': {
     *         'total'    : ...,
     *         'mean'     : ...,
     *         'variance' : ...,
     *         'histogram': [
     *             ...
     *         ]
     *     },
     *     'run': {
     *         'total'    : ...,
     *         'mean'     : ...,
     *         'variance' : ...,
     *         'histogram': [
     *             ...
     *         ]
     *     },
     * }
     * </code>
     *
     * @param  int $date The date for which stats to retrieve as a unix timestamp.
     * @return array
     *
     * @throws QlessException
     */
    public function stats(?int $date = null): array
    {
        $date = $date ?: time();

        return json_decode($this->client->stats($this->name, $date), true);
    }

    /**
     * Pauses this queue so it will not process any more Jobs.
     *
     * @return void
     */
    public function pause(): void
    {
        $this->client->pause($this->name);
    }

    /**
     * Resumes the queue so it will continue processing jobs.
     *
     * @return void
     */
    public function resume(): void
    {
        $this->client->unpause($this->name);
    }

    /**
     * Checks if this queue is paused.
     *
     * @return bool
     */
    public function isPaused(): bool
    {
        return (bool) $this->client->paused($this->name);
    }

    /**
     * Gets this queue name.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->name;
    }
}

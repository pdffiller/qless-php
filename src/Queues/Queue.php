<?php

namespace Qless\Queues;

use Qless\Client;
use Qless\Events\User\Queue as QueueEvent;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Qless\Exceptions\UnknownPropertyException;
use Qless\Jobs\BaseJob;
use Qless\Jobs\JobData;
use Ramsey\Uuid\Uuid;

/**
 * Qless\Queues\Queue
 *
 * @package Qless\Queues
 *
 * @property int $heartbeat get / set the heartbeat timeout for the queue
 */
class Queue implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

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

        $this->setEventsManager($this->client->getEventsManager());
    }

    /**
     * Put the described job in this queue.
     *
     * Either create a new job in the provided queue with the provided attributes,
     * or move that job into that queue. If the job is being serviced by a worker,
     * subsequent attempts by that worker to either `heartbeat` or `complete` the
     * job should fail and return `false`.
     *
     * @param  string        $className The class with the job perform method.
     * @param  array         $data      An array of parameters for job.
     * @param  string|null   $jid       The specified job id, if not a specified, a jid will be generated.
     * @param  int|null      $delay     The specified delay to run job.
     * @param  int|null      $retries   Number of retries allowed.
     * @param  int|null      $priority  A greater priority will execute before jobs of lower priority.
     * @param  string[]|null $tags      An array of tags to add to the job.
     * @param  string[]|null $depends   A list of JIDs this job must wait on before executing.
     * @return string The job identifier.
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    public function put(
        string $className,
        array $data,
        ?string $jid = null,
        ?int $delay = null,
        ?int $retries = null,
        ?int $priority = null,
        ?array $tags = null,
        ?array $depends = null
    ) {
        try {
            $jid = $jid ?: str_replace('-', '', Uuid::uuid4()->toString());
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        $data = new JobData($data);
        $this->getEventsManager()->fire(new QueueEvent\BeforeEnqueue($this, $jid, $data, $className));

        if (!$putData = json_encode($data, JSON_UNESCAPED_SLASHES)) {
            throw new RuntimeException(
                sprintf(
                    'Unable to encode payload to put the described job "%s" to the "%s" queue.',
                    $jid,
                    $this->name
                )
            );
        }

        $jid = $this->client->put(
            '',
            $this->name,
            $jid,
            $className,
            $putData,
            is_null($delay) ? 0 : $delay,
            'priority',
            is_null($priority) ? 0 : $priority,
            'tags',
            json_encode($tags ?: [], JSON_UNESCAPED_SLASHES),
            'retries',
            is_null($retries) ? 5 : $retries,
            'depends',
            json_encode($depends ?: [], JSON_UNESCAPED_SLASHES)
        );

        $this->getEventsManager()->fire(new QueueEvent\AfterEnqueue($this, $jid, $data->toArray(), $className));

        return $jid;
    }

    /**
     * Get the next job on this queue.
     *
     * @param string|null $worker  Worker name popping the job.
     * @param int         $numJobs Number of jobs to pop off of the queue.
     *
     * @return BaseJob|BaseJob[]|null
     *
     * @throws QlessException
     */
    public function pop(?string $worker = null, ?int $numJobs = null)
    {
        $workerName = $worker ?: $this->client->getWorkerName();
        $jids = json_decode($this->client->pop($this->name, $workerName, $numJobs ?: 1), true);

        $jobs = [];
        array_map(function (array $data) use (&$jobs) {
            $job = new BaseJob($this->client, $data);
            $job->setEventsManager($this->getEventsManager());

            $jobs[] = $job;
        }, $jids ?: []);

        return $numJobs === null ? array_shift($jobs) : $jobs;
    }

    /**
     * Make a recurring job in this queue.
     *
     * @param  string      $className The class with the job perform method.
     * @param  array       $data      An array of parameters for job.
     * @param  int|null    $interval  The recurring interval in seconds.
     * @param  int|null    $offset    A delay before the first run in seconds.
     * @param  string|null $jid       The specified job id, if not a specified, a jid will be generated.
     * @param  int|null    $retries   Number of times the job can retry when it runs.
     * @param  int|null    $priority  A negative priority will run sooner.
     * @param  int|null    $backlog
     * @param  array|null  $tags      An array of tags to add to the job.
     *
     * @return string
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    public function recur(
        string $className,
        array $data,
        ?int $interval = null,
        ?int $offset = null,
        ?string $jid = null,
        ?int $retries = null,
        ?int $priority = null,
        ?int $backlog = null,
        ?array $tags = null
    ) {
        try {
            $jid = $jid ?: Uuid::uuid4()->toString();
        } catch (\Exception $e) {
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
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
            $className,
            $data,
            'interval',
            is_null($interval) ? 60 : $interval,
            is_null($offset) ? 0 : $offset,
            'priority',
            is_null($priority) ? 0 : $priority,
            'tags',
            json_encode($tags ?: [], JSON_UNESCAPED_SLASHES),
            'retries',
            is_null($retries) ? 5 : $retries,
            'backlog',
            is_null($backlog) ? 0 : $backlog
        );
    }


    /**
     * Cancels a job using the specified identifier.
     *
     * @param string $jid
     *
     * @return array
     */
    public function cancel(string $jid): array
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
     * @throws UnknownPropertyException
     * @throws QlessException
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'heartbeat':
                $fallback = $this->client->config->get('heartbeat', 60);

                return (int) $this->client->config->get("{$this->name}-heartbeat", $fallback);
            default:
                throw new UnknownPropertyException('Getting unknown property: ' . self::class . '::' . $name);
        }
    }

    /**
     * The magic setter to update Queue's properties.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     *
     * @throws UnknownPropertyException
     * @throws InvalidArgumentException
     * @throws QlessException
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
                throw new UnknownPropertyException('Setting unknown property: ' . self::class . '::' . $name);
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
        $stat = json_decode($this->client->queues($this->name), true);

        return isset($stat['name']) && $stat['name'] === $this->name && $stat['paused'] == true;
    }

    /**
     * @param string $topicPattern
     * @return bool
     */
    public function subscribe(string $topicPattern): bool
    {
        $subscriptions = $this->client->subscription($this->name, 'add', $topicPattern);
        $subscriptions = json_decode($subscriptions, true);

        return in_array($topicPattern, $subscriptions);
    }

    /**
     * @param string $topicPattern
     * @return bool
     */
    public function unSubscribe(string $topicPattern): bool
    {
        return $this->client->subscription($this->name, 'remove', $topicPattern) == 'true';
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

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
use Qless\Queues\DTO\BackoffStrategyDTO;
use Qless\Support\PropertyAccessor;
use Ramsey\Uuid\Uuid;

/**
 * Qless\Queues\Queue
 *
 * @package Qless\Queues
 *
 * @property int $heartbeat get / set the heartbeat timeout for the queue
 * @property-read JobCollection $jobs
 */
class Queue implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait, PropertyAccessor;

    /** @var Client */
    private $client;

    /** @var string */
    private $name;

    /** @var JobCollection */
    private $jobs;

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

        $this->jobs = new JobCollection($this->name, $client);

        $this->setEventsManager($this->client->getEventsManager());

        if ($this->client->config->get('sync-enabled')) {
            $this->registerSyncCompleteEvent();
        }
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
        ?array $depends = null,
        ?BackoffStrategyDTO $backoffStrategyDTO = null
    ): string {
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
            json_encode($depends ?: [], JSON_UNESCAPED_SLASHES),
            'backoff',
            json_encode($backoffStrategyDTO ? $backoffStrategyDTO->toArray() : [], JSON_UNESCAPED_SLASHES)
        );

        $this->getEventsManager()->fire(new QueueEvent\AfterEnqueue($this, $jid, $data->toArray(), $className));

        return $jid;
    }

    /**
     * Get the next job on this queue.
     *
     * @param string|null $worker  Worker name popping the job.
     * @param int|null         $numJobs Number of jobs to pop off of the queue.
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
     * Get job by JID from this queue.
     *
     * @param string $jid
     * @param string|null $worker
     * @return BaseJob|null
     */
    public function popByJid(string $jid, ?string $worker = null): ?BaseJob
    {
        $workerName = $worker ?: $this->client->getWorkerName();
        $data = json_decode($this->client->popByJid($this->name, $jid, $workerName), true);
        $jobData = array_reduce($data, 'array_merge', []); //unwrap nested array

        if (isset($jobData['jid']) === false) {
            return null;
        }

        if ($jobData['jid'] === $jid) {
            $job = new BaseJob($this->client, $jobData);
            $job->setEventsManager($this->getEventsManager());
        }

        return $job ?? null;
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
    ): string {
        try {
            $jid = $jid ?: str_replace('-', '', Uuid::uuid4()->toString());
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
    public function unrecur(string $jid): int
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
     * @param  int|null $date The date for which stats to retrieve as a unix timestamp.
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
     * Forget this queue, removing it from the backing store
     *
     * @param bool $force Force the queue to be removed, ignoring any jobs held in the queue.
     *
     * @return void
     * @throws QlessException If the queue is not empty.
     */
    public function forget(bool $force = false): void
    {
        if (!$force && $this->length() > 0) {
            throw new QlessException('Queue is not empty');
        }

        $this->client->call('queue.forget', $this->name);
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

        return isset($stat['name']) && $stat['name'] === $this->name && $stat['paused'] === true;
    }

    /**
     * @param string $topicPattern
     * @return bool
     */
    public function subscribe(string $topicPattern): bool
    {
        $subscriptions = $this->client->subscription($this->name, 'add', $topicPattern);
        $subscriptions = json_decode($subscriptions, true);

        return in_array($topicPattern, $subscriptions, true);
    }

    /**
     * @param string $topicPattern
     * @return bool
     */
    public function unSubscribe(string $topicPattern): bool
    {
        return $this->client->subscription($this->name, 'remove', $topicPattern) === 'true';
    }

    /**
     * @return int
     */
    public function getHeartbeat(): int
    {
        $fallback = $this->client->config->get('heartbeat', 60);

        return (int) $this->client->config->get("{$this->name}-heartbeat", $fallback);
    }

    /**
     * @param int $value
     */
    public function setHeartbeat(int $value): void
    {
        $this->client
            ->config
            ->set("{$this->name}-heartbeat", $value);
    }

    /**
     * @return JobCollection
     */
    public function getJobs(): JobCollection
    {
        return $this->jobs;
    }

    /**
     *
     */
    protected function unsetHeartbeat(): void
    {
        try {
            $this->client
                ->config
                ->clear("{$this->name}-heartbeat");
        } catch (\Throwable $e) {
            // The unset shouldn't throw any exception. Thus do nothing
        }
    }

    /**
     * Immediately handle job if sync mode enabled
     */
    private function registerSyncCompleteEvent(): void
    {
        $this->getEventsManager()
            ->attach(QueueEvent\AfterEnqueue::getName(), function (QueueEvent\AfterEnqueue $event) {
                if (!$this->client->config->get('sync-enabled')) {
                    return;
                }
                $job = $this->popByJid($event->getJid());
                if ($job !== null) {
                    $job->perform();
                }
            });
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

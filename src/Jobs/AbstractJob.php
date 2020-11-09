<?php

namespace Qless\Jobs;

use Qless\Client;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Support\PropertyAccessor;

/**
 * Qless\Jobs\AbstractJob
 *
 * The base class for both BaseJob and RecurringJob.
 *
 * @property-read string $klass
 * @property-read string $queue
 * @property-read string $jid
 * @property int $priority
 * @property-read string[] $tags
 * @property-read int $retries
 * @property JobData $data
 *
 * @package Qless\Jobs
 */
abstract class AbstractJob implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait, PropertyAccessor;

    /**
     * The job id.
     *
     * @var string
     */
    private $jid = '';

    /**
     * The class of the job.
     *
     * @var string
     */
    private $klass = '';

    /**
     * The queue the job is in.
     *
     * @var string
     */
    private $queue = '';

    /**
     * Array of tags for this job.
     *
     * @var string[]
     */
    private $tags = [];

    /**
     * The priority of this job.
     *
     * var int
     */
    private $priority = 0;

    /**
     * The number of retries originally requested.
     *
     * @var int
     */
    private $retries = 0;

    /**
     * The data for the job.
     *
     * @var JobData
     */
    private $data;

    /** @var array */
    protected $rawData = [];

    /** @var JobFactory */
    protected $jobFactory;

    /** @var Client */
    protected $client;

    /**
     * Job constructor.
     *
     * @param Client $client
     * @param string $jid
     * @param array  $data
     */
    public function __construct(Client $client, string $jid, array $data)
    {
        $this->jobFactory = new JobFactory();
        $this->jobFactory->setEventsManager($client->getEventsManager());

        $this->client = $client;
        $this->jid = $jid;
        $this->rawData = $data;

        $this->setKlass($data['klass']);
        $this->setQueue($data['queue']);

        $this->setRetries((int) ($data['retries'] ?? 0));
        $this->setPriority((int) ($data['priority'] ?? 0));
        $this->setTags($data['tags'] ?? []);

        $this->setData(new JobData(json_decode($data['data'], true) ?: []));
    }

    /**
     * Gets Job's ID.
     *
     * @return string
     */
    public function getJid(): string
    {
        return $this->jid;
    }

    /**
     * Gets Job's klass.
     *
     * @return string
     */
    public function getKlass(): string
    {
        return $this->klass;
    }

    /**
     * Gets Job's queue.
     *
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * Gets Job's tags.
     *
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Gets Job's priority.
     *
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Gets Job's retries.
     *
     * @return int
     */
    public function getRetries(): int
    {
        return $this->retries;
    }

    /**
     * Gets Job's data.
     *
     * @return JobData
     */
    public function getData(): JobData
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function getFailure(): array
    {
        return $this->rawData['failure'] ?? [];
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->rawData['state'] ?? '';
    }

    /**
     * Add the specified tags to this job.
     *
     * @param  string ...$tags A list of tags to to add to this job.
     * @return void
     */
    public function tag(...$tags): void
    {
        $response = call_user_func_array(
            [$this->client, 'call'],
            array_merge(['tag', 'add', $this->jid], array_values(func_get_args()))
        );

        $this->setTags(json_decode($response, true));
    }

    /**
     * Remove the specified tags to this job.
     *
     * @param  string ...$tags A list of tags to remove from this job.
     * @return void
     */
    public function untag(...$tags): void
    {
        $response = call_user_func_array(
            [$this->client, 'call'],
            array_merge(['tag', 'remove', $this->jid], array_values(func_get_args()))
        );

        $this->setTags(json_decode($response, true));
    }

    /**
     * Sets Job's data.
     *
     * @param  JobData|array|string $data
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function setData($data): void
    {
        if (is_array($data) === false && is_string($data) === false && $data instanceof JobData === false) {
            throw new InvalidArgumentException(
                sprintf(
                    "Job's data must be either an array, or a JobData instance, or a JSON string, %s given.",
                    gettype($data)
                )
            );
        }

        if (is_array($data) === true) {
            $data =  new JobData($data);
        } elseif (is_string($data) === true) {
            // Assume this is JSON
            $data =  new JobData(json_decode($data, true));
        }

        $this->data = $data;
    }

    /**
     * Sets Job's priority.
     *
     * @param  int $priority
     * @return void
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * Sets Job's retries.
     *
     * @param  int $retries
     * @return void
     */
    public function setRetries(int $retries): void
    {
        $this->retries = $retries;
    }

    /**
     * Sets Job's klass.
     *
     * @param  string $className
     * @return void
     */
    public function setKlass(string $className): void
    {
        $this->klass = $className;
    }

    /**
     * Sets Job's queue name.
     *
     * @param  string $queue
     * @return void
     */
    public function setQueue(string $queue): void
    {
        $this->queue = $queue;
    }

    /**
     * Sets Job's tags.
     *
     * @param  array $tags
     * @return void
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}

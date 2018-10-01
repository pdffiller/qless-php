<?php

namespace Qless\Jobs;

use Qless\Client;
use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Qless\Exceptions\UnknownPropertyException;

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
    use EventsManagerAwareTrait;

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

        $this->setRetries((int) $data['retries'] ?? 0);
        $this->setPriority((int) $data['priority'] ?? 0);

        $this->setData(new JobData(json_decode($data['data'], true) ?: []));

        $this->tags = $data['tags'] ?? [];
    }

    /**
     * Gets the internal Job's properties.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $job->property;`.
     *
     * @param  string $name
     * @return mixed
     *
     * @throws UnknownPropertyException
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'jid':
                return $this->jid;
            case 'klass':
                return $this->klass;
            case 'queue':
                return $this->queue;
            case 'tags':
                return $this->tags;
            case 'priority':
                return $this->priority;
            case 'retries':
                return $this->retries;
            case 'data':
                return $this->data;
            default:
                throw new UnknownPropertyException('Getting unknown property: ' . get_class($this) . '::' . $name);
        }
    }

    /**
     * The magic setter to update Job's properties.
     *
     * @param  string $name
     * @param  mixed  $value
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     * @throws UnknownPropertyException
     */
    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'priority':
                $this->setJobPriority($value);
                break;
            default:
                throw new UnknownPropertyException('Setting unknown property: ' . get_class($this) . '::' . $name);
        }
    }

    /**
     * Sets Job's priority.
     *
     * @param  int $priority
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    abstract protected function setJobPriority(int $priority): void;

    /**
     * Add the specified tags to this job.
     *
     * @param  string ...$tags A list of tags to remove from this job.
     * @return void
     */
    public function tag(...$tags): void
    {
        $tags = func_get_args();
        $response = call_user_func_array([$this->client, 'call'], array_merge(['tag', 'add', $this->jid], $tags));

        $this->tags = json_decode($response, true);
    }

    /**
     * Remove the specified tags to this job
     *
     * @param  string ...$tags A list of tags to to add to this job.
     * @return void
     */
    public function untag(...$tags): void
    {
        $tags = func_get_args();
        $this->tags = json_decode(
            call_user_func_array([$this->client, 'call'], array_merge(['tag', 'remove', $this->jid], $tags)),
            true
        );
    }

    /**
     * Cancel a job.
     *
     * It will be deleted from the system, the thinking being that if you don't want
     * to do any work on it, it shouldn't be in the queuing system. Optionally cancels all jobs's dependents.
     *
     * @param  bool $dependents true if associated dependents should also be cancelled
     * @return array
     */
    public function cancel($dependents = false): array
    {
        if ($dependents && !empty($this->rawData['dependents'])) {
            return call_user_func_array(
                [$this->client, 'cancel'],
                array_merge([$this->jid], $this->rawData['dependents'])
            );
        }

        return $this->client->cancel($this->jid);
    }

    /**
     * Sets Job's data.
     *
     * @param  JobData $data
     * @return void
     */
    protected function setData(JobData $data): void
    {
        $this->data = $data;
    }

    /**
     * Sets Job's priority.
     *
     * @param  int $priority
     * @return void
     */
    protected function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * Sets Job's retries.
     *
     * @param  int $retries
     * @return void
     */
    protected function setRetries(int $retries): void
    {
        $this->retries = $retries;
    }

    /**
     * Sets Job's klass.
     *
     * @param  string $className
     * @return void
     */
    protected function setKlass(string $className): void
    {
        $this->klass = $className;
    }

    /**
     * Sets Job's queue name.
     *
     * @param  string $queue
     * @return void
     */
    protected function setQueue(string $queue): void
    {
        $this->queue = $queue;
    }
}

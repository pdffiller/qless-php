<?php

namespace Qless\Jobs;

use Qless\Client;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Qless\Exceptions\UnknownPropertyException;

/**
 * Qless\Jobs\RecurringJob
 *
 * Wraps a recurring job.
 *
 * @property int $interval
 * @property-read int $count
 * @property int $backlog
 * @property int $retries
 * @property string $klass
 *
 * @package Qless\Jobs
 */
class RecurringJob extends AbstractJob
{
    /** @var int  */
    private $interval = 60;

    /** @var int  */
    private $count = 0;

    /** @var int  */
    private $backlog = 0;

    /**
     * Job constructor.
     *
     * @param Client $client
     * @param array $data
     */
    public function __construct(Client $client, array $data)
    {
        parent::__construct($client, $data['jid'], $data);

        $this->interval = $data['interval'] ?? 60;
        $this->count = (int) $data['count'] ?? 0;
        $this->backlog = (int) $data['backlog'] ?? 0;
    }

    /**
     * {@inheritdoc}
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
            case 'interval':
                return $this->interval;
            case 'count':
                return $this->count;
            case 'backlog':
                return $this->backlog;
            default:
                return parent::__get($name);
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
     * @throws InvalidArgumentException
     */
    public function __set(string $name, $value)
    {
        switch ($name) {
            case 'retries':
                $this->setJobRetries($value);
                break;
            case 'interval':
                $this->setJobInterval($value);
                break;
            case 'data':
                $this->setJobData($value);
                break;
            case 'klass':
                $this->setJobKlass($value);
                break;
            case 'backlog':
                $this->setJobBacklog($value);
                break;
            default:
                parent::__set($name, $value);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  int $priority
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    protected function setJobPriority(int $priority): void
    {
        if ($this->client->call('recur.update', $this->jid, 'priority', $priority)) {
            $this->setPriority($priority);
        }
    }

    /**
     * Sets Job's data.
     *
     * @param  string|array|JobData $data
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws QlessException
     * @throws RuntimeException
     */
    protected function setJobData($data): void
    {
        if (is_array($data) || $data instanceof JobData) {
            $update = json_encode($data, JSON_UNESCAPED_SLASHES);
        } else if (is_string($data)) {
            // Assume this is JSON
            $update = $data;
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    "Job's data must be either an array, or a JobData instance, or a JSON string, %s given.",
                    gettype($data)
                )
            );
        }

        if ($this->client->call('recur.update', $this->jid, 'data', $update)) {
            $this->setData(new JobData(json_decode($update, true)));
        }
    }

    /**
     * Sets Job's backlog.
     *
     * @param  int $backlog
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    private function setJobBacklog(int $backlog): void
    {
        if ($this->client->call('recur.update', $this->jid, 'backlog', $backlog)) {
            $this->backlog = $backlog;
        }
    }

    /**
     * Sets Job's klass.
     *
     * @param  string $className
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    private function setJobKlass(string $className): void
    {
        if ($this->client->call('recur.update', $this->jid, 'klass', $className)) {
            $this->setKlass($className);
        }
    }

    /**
     * Sets Job's retries.
     *
     * @param  int $retries
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    private function setJobRetries(int $retries): void
    {
        if ($this->client->call('recur.update', $this->jid, 'retries', $retries)) {
            $this->setRetries($retries);
        }
    }

    /**
     * Sets Job's interval.
     *
     * @param  int $interval
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    private function setJobInterval(int $interval): void
    {
        if ($this->client->call('recur.update', $this->jid, 'interval', $interval)) {
            $this->interval = $interval;
        }
    }

    /**
     * Sets Job's queue name.
     *
     * @param  string $queue
     * @return void
     */
    public function move(string $queue): void
    {
        if ($this->client->call('recur.update', $this->jid, 'queue', $queue)) {
            $this->setQueue($queue);
        }
    }
}

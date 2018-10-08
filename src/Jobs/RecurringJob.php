<?php

namespace Qless\Jobs;

use Qless\Client;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;

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

        $this->interval = (int) $data['interval'] ?? 60;
        $this->count = (int) $data['count'] ?? 0;
        $this->backlog = (int) $data['backlog'] ?? 0;
    }

    /**
     * Gets Job's interval.
     *
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * Gets Job's count.
     *
     * @return int
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Gets Job's backlog.
     *
     * @return int
     */
    public function getBacklog(): int
    {
        return $this->backlog;
    }

    /**
     * {@inheritdoc}
     *
     * @param  int $retries
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    public function setRetries(int $retries): void
    {
        if ($this->client->call('recur.update', $this->jid, 'retries', $retries)) {
            parent::setRetries($retries);
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
    public function setInterval(int $interval): void
    {
        if ($this->client->call('recur.update', $this->jid, 'interval', $interval)) {
            $this->interval = $interval;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  JobData|array|string $data
     * @return void
     *
     * @throws InvalidArgumentException
     * @throws QlessException
     * @throws RuntimeException
     */
    public function setData($data): void
    {
        if (is_array($data) || $data instanceof JobData) {
            $update = json_encode($data, JSON_UNESCAPED_SLASHES);
        } elseif (is_string($data)) {
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
            if ($data instanceof JobData) {
                parent::setData($data);
            } elseif (is_array($data)) {
                parent::setData(new JobData($data));
            } else {
                parent::setData(new JobData(json_decode($data, true) ?: []));
            }
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $className
     * @return void
     *
     * @throws QlessException
     * @throws RuntimeException
     */
    public function setKlass(string $className): void
    {
        if ($this->client->call('recur.update', $this->jid, 'klass', $className)) {
            parent::setKlass($className);
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
    public function setBacklog(int $backlog): void
    {
        if ($this->client->call('recur.update', $this->jid, 'backlog', $backlog)) {
            $this->backlog = $backlog;
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
    public function setPriority(int $priority): void
    {
        if ($this->client->call('recur.update', $this->jid, 'priority', $priority)) {
            parent::setPriority($priority);
        }
    }

    /**
     * Sets Job's queue name.
     *
     * @param  string $queue
     * @return void
     */
    public function requeue(string $queue): void
    {
        if ($this->client->call('recur.update', $this->jid, 'queue', $queue)) {
            $this->setQueue($queue);
        }
    }

    /**
     * Cancel a job.
     *
     * @return int
     */
    public function cancel(): int
    {
        return $this->client->call('unrecur', $this->jid);
    }

    /**
     * {@inheritdoc}
     *
     * @param  string ...$tags A list of tags to to add to this job.
     * @return void
     */
    public function tag(...$tags): void
    {
        call_user_func_array(
            [$this->client, 'call'],
            array_merge(['recur.tag', $this->jid], array_values(func_get_args()))
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param  string ...$tags A list of tags to remove from this job.
     * @return void
     */
    public function untag(...$tags): void
    {
        call_user_func_array(
            [$this->client, 'call'],
            array_merge(['recur.untag', $this->jid], array_values(func_get_args()))
        );
    }
}

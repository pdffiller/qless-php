<?php

namespace Qless\Jobs;

use Qless\Client;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Qless\Exceptions\UnknownPropertyException;

/**
 * Qless\Jobs\RecurringJob
 *
 * Wraps a recurring job.
 *
 * @property-read int $interval
 * @property-read int $count
 * @property-read int $backlog
 *
 * @package Qless\Jobs
 */
class RecurringJob extends AbstractJob
{
    /** @var int  */
    private $interval = 0;

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

        $this->interval = $data['interval'] ?? 0;
        $this->count = (int) $data['count'] ?? 0;
        $this->backlog = (int) $data['backlog'] ?? 0;
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
            $this->priority = $priority;
        }
    }
}

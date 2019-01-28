<?php

namespace Qless\Jobs\Reservers;

use Qless\Exceptions\InvalidArgumentException;
use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\Options\ReserverOptionsInterface;
use Qless\Queues\Queue;

/**
 * Qless\Jobs\Reservers\RoundRobinReserver
 *
 * @package Qless\Jobs\Reservers
 */
class RoundRobinReserver extends AbstractReserver implements ReserverInterface
{
    /** @var int */
    private $numQueues = 0;

    /** @var int  */
    private $lastIndex = 0;

    /**
     * {@inheritdoc}
     *
     * @var string
     */
    const TYPE_DESCRIPTION = 'round robin';

    /**
     * Instantiate a new reserver, given a list of queues that it should be working on.
     *
     * @param ReserverOptionsInterface $options
     */
    public function __construct(ReserverOptionsInterface $options) {
        parent::__construct($options);

        $this->numQueues = count($this->options->getQueues());
        $this->lastIndex = $this->numQueues - 1;
    }

    /**
     * {@inheritdoc}
     *
     * @return BaseJob|null
     */
    final public function reserve(): ?BaseJob
    {
        $this->beforeWork();

        $this->logger->debug('Attempting to reserve a job using {reserver} reserver', [
            'reserver' => $this->getDescription(),
        ]);

        for ($i = 0; $i < $this->numQueues; ++$i) {
            $queue = $this->nextQueue();

            /** @var \Qless\Jobs\BaseJob|null $job */
            $job = $queue->pop($this->options->getWorker());
            if ($job !== null) {
                $this->logger->info('Found a job on {queue}', ['queue' => (string) $queue]);
                return $job;
            }
        }

        return null;
    }

    private function nextQueue(): Queue
    {
        $this->lastIndex = ($this->lastIndex + 1) % $this->numQueues;

        return $this->queues[$this->lastIndex];
    }
}

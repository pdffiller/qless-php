<?php

namespace  Qless\Jobs\Reservers;

use Qless\Exceptions\InvalidArgumentException;
use Qless\Jobs\BaseJob;
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

    const TYPE_DESCRIPTION = 'round robin';

    /**
     * {@inheritdoc}
     *
     * @param Queue[]     $queues
     * @param string|null $worker
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $queues, ?string $worker = null)
    {
        parent::__construct($queues, $worker);

        $this->numQueues = count($this->queues);
        $this->lastIndex = $this->numQueues - 1;
    }

    /**
     * {@inheritdoc}
     *
     * @return BaseJob|null
     */
    final public function reserve(): ?BaseJob
    {
        for ($i = 0; $i < $this->numQueues; ++$i) {
            /** @var \Qless\Jobs\BaseJob|null $job */
            $job = $this->nextQueue()->pop($this->worker);
            if ($job !== null) {
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

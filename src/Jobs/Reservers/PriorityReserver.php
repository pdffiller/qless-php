<?php

namespace Qless\Jobs\Reservers;

use Qless\Queues\Queue;

/**
 * Class PriorityReserver
 * @package Qless\Jobs\Reservers
 */
class PriorityReserver extends AbstractReserver
{
    /** @var array */
    private $priorities = [];

    /** @var int */
    private $minPriority;

    /** @var int */
    private $maxPriority;

    /**
     * {@inheritdoc}
     *
     * @var string
     */
    const TYPE_DESCRIPTION = 'priority';

    /**
     * Default priority of queue
     */
    const DEFAULT_PRIORITY = 5;

    /**
     * Default min processed priority
     */
    const DEFAULT_MIN_PRIORITY = 1;

    /**
     * Default max processed priority
     */
    const DEFAULT_MAX_PRIORITY = 8;

    /**
     * @param array $priorities
     */
    public function setPriorities(array $priorities): void
    {
        $this->priorities = $priorities;
    }

    /**
     * @param int $minPriority
     */
    public function setMinPriority(int $minPriority): void
    {
        $this->minPriority = $minPriority;
    }

    /**
     * @param int $maxPriority
     */
    public function setMaxPriority(int $maxPriority): void
    {
        $this->maxPriority = $maxPriority;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function beforeWork(): void
    {
        parent::beforeWork();

        $this->filterQueuesByPriorityRange();

        // Random for queues with equal priorities
        shuffle($this->queues);

        $priorities = $this->priorities;

        usort($this->queues, function (Queue $firstQueue, Queue $secondQueue) use ($priorities) {
            $priorityFirst = $priorities[(string) $firstQueue] ?? self::DEFAULT_PRIORITY;
            $prioritySecond = $priorities[(string) $secondQueue] ?? self::DEFAULT_PRIORITY;

            return $prioritySecond - $priorityFirst;
        });

        if (empty($this->queues) == false) {
            $this->logger->info(
                'Monitoring the following queues: {queues}',
                ['queues' => implode(', ', $this->queues)]
            );
        }
    }

    private function filterQueuesByPriorityRange(): void
    {
        $this->initPriorityRange();

        foreach ($this->queues as $k => $queue) {
            $priority = $priorities[(string) $queue] ?? self::DEFAULT_PRIORITY;
            if ($priority < $this->minPriority || $priority > $this->maxPriority) {
                unset($this->queues[$k]);
            }
        }
    }

    private function initPriorityRange(): void
    {
        if (empty($this->minPriority)) {
            $this->minPriority = self::DEFAULT_MIN_PRIORITY;
        }

        if (empty($this->maxPriority)) {
            $this->maxPriority = self::DEFAULT_MAX_PRIORITY;
        }
    }
}

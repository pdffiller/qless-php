<?php

namespace Qless\Jobs\Reservers;

use Qless\Queues\Queue;

/**
 * Class PriorityReserver
 * @package Qless\Jobs\Reservers
 */
class PriorityReserver extends AbstractReserver implements ReserverInterface
{
    /** @var array */
    private $priorities = [];

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
     * @param array $priorities
     */
    public function setPriorities(array $priorities): void
    {
        $this->priorities = $priorities;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function beforeWork(): void
    {
        parent::beforeWork();

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
}

<?php

namespace Qless\Jobs\Reservers;

/**
 * Qless\Jobs\Reservers\ShuffledRoundRobinReserver
 *
 * @package Qless\Jobs\Reservers
 */
class ShuffledRoundRobinReserver extends RoundRobinReserver
{
    /**
     * {@inheritdoc}
     *
     * @var string
     */
    const TYPE_DESCRIPTION = 'shuffled round robin';

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function beforeWork(): void
    {
        parent::beforeWork();

        shuffle($this->queues);

        if (empty($this->queues) == false) {
            $this->logger->info(
                'Monitoring the following queues: {queues}',
                ['queues' => implode(', ', $this->queues)]
            );
        }
    }
}

<?php

namespace Qless\Jobs\Reservers;

/**
 * Qless\Jobs\Reservers\OrderedReserver
 *
 * @package Qless\Jobs\Reservers
 */
class OrderedReserver extends AbstractReserver
{
    /**
     * {@inheritdoc}
     *
     * @var string
     */
    const TYPE_DESCRIPTION = 'ordered';

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function beforeWork(): void
    {
        parent::beforeWork();

        sort($this->queues, SORT_NATURAL);

        $this->resetDescription();

        if (empty($this->queues) === false) {
            $this->logger->info(
                'Monitoring the following queues: {queues}',
                ['queues' => implode(', ', $this->queues)]
            );
        }
    }
}

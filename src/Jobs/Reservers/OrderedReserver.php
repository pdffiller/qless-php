<?php

namespace Qless\Jobs\Reservers;

/**
 * Qless\Jobs\Reservers\OrderedReserver
 *
 * @package Qless\Jobs\Reservers
 */
class OrderedReserver extends AbstractReserver implements ReserverInterface
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

        sort($this->options->getQueues(), SORT_NATURAL);

        $this->resetDescription();

        if (empty($this->options->getQueues()) == false) {
            $this->logger->info(
                'Monitoring the following queues: {queues}',
                ['queues' => implode(', ', $this->options->getQueues())]
            );
        }
    }
}

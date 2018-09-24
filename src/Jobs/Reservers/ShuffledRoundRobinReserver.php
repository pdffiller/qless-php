<?php

namespace  Qless\Jobs\Reservers;

/**
 * Qless\Jobs\Reservers\ShuffledRoundRobinReserver
 *
 * @package Qless\Jobs\Reservers
 */
class ShuffledRoundRobinReserver extends RoundRobinReserver
{
    const TYPE_DESCRIPTION = 'shuffled round robin';

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function beforeWork(): void
    {
        shuffle($this->queues);
        $this->resetDescription();
    }
}

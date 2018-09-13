<?php

namespace  Qless\Jobs\Reservers;

use Qless\Jobs\Job;

/**
 * Qless\Jobs\Reservers\OrderedReserver
 *
 * @package Qless\Jobs\Reservers
 */
class OrderedReserver extends AbstractReserver implements ReserverInterface
{
    const TYPE_DESCRIPTION = 'ordered';

    /**
     * {@inheritdoc}
     *
     * @return Job|null
     */
    final public function reserve(): ?Job
    {
        foreach ($this->queues as $queue) {
            /** @var \Qless\Jobs\Job|null $job */
            $job = $queue->pop($this->worker);
            if ($job !== null) {
                return $job;
            }
        }

        return null;
    }
}

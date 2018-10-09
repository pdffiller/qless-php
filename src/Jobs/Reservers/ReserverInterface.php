<?php

namespace  Qless\Jobs\Reservers;

use Psr\Log\LoggerAwareInterface;
use Qless\Jobs\BaseJob;
use Qless\Queues\Queue;

/**
 * Qless\Jobs\Reservers\ReserverInterface
 *
 * @package Qless\Jobs\Reservers
 */
interface ReserverInterface extends LoggerAwareInterface
{
    /**
     * Gets reserver's description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Gets a list of the using queues.
     *
     * @return Queue[]
     */
    public function getQueues(): array;

    /**
     * Reserve a job to perform work.
     *
     * @return BaseJob|null
     */
    public function reserve(): ?BaseJob;

    /**
     * Preparing before work.
     *
     * @return void
     */
    public function beforeWork(): void;
}

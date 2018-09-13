<?php

namespace  Qless\Jobs\Reservers;

use Qless\Exceptions\InvalidArgumentException;
use Qless\Queue;

/**
 * Qless\Jobs\Reservers\AbstractReserver
 *
 * Abstract Job reserver. Different reservers use different
 * strategies for which order jobs are popped off of queues.
 *
 * @package Qless\Jobs\Reservers
 */
abstract class AbstractReserver implements ReserverInterface
{
    /** @var Queue[] */
    protected $queues = [];

    /** @var string|null */
    protected $worker;

    /** @var string|null */
    protected $description;

    protected const TYPE_DESCRIPTION = 'undefined';

    /**
     * Resever constructor.
     *
     * @param Queue[]     $queues
     * @param string|null $worker
     *
     * @throws InvalidArgumentException
     */
    public function __construct(array $queues, ?string $worker = null)
    {
        foreach ($queues as $queue) {
            if ($queue instanceof Queue == false) {
                $format = 'Failed to initialize the resever:  ' .
                    'The "%s" resever should be initialized using an array of "%s" instances, the "%s" given.';

                throw new InvalidArgumentException(
                    sprintf(
                        $format,
                        static::class,
                        Queue::class,
                        is_object($queue) ? get_class($queue) : gettype($queue)
                    )
                );
            }

            $this->queues[] = $queue;
        }

        $this->worker = $worker;
    }

    /**
     * {@inheritdoc}
     *
     * @return Queue[]
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     * @codeCoverageIgnoreStart
     */
    public function beforeFork(): void
    {
        // nothing to do
    }
    // @codeCoverageIgnoreEnd

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getDescription(): string
    {
        if ($this->description === null) {
            $this->description = $this->initializeDescription($this->queues);
        }

        return $this->description;
    }

    protected function initializeDescription(array $queues): string
    {
        $names = array_map(function (Queue $queue) {
            return (string) $queue;
        }, $queues);

        return  trim(implode(', ', $names) . ' (' . static::TYPE_DESCRIPTION . ')');
    }

    protected function resetDescription(): void
    {
        $this->description = null;
    }
}

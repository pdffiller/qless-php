<?php

namespace  Qless\Jobs\Reservers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Queues\Queue;
use Qless\Queues\Collection;

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

    /**
     * Current reserver type description.
     *
     * @var string|null
     */
    protected $description;

    /**
     * Logging object that implements the PSR-3 LoggerInterface.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Whether the reserver should refresh the list of queues, or just go with the queues it has been given.
     *
     * @var bool
     */
    protected $refreshQueues = false;

    /**
     * A specification to reserve queues.
     *
     * @var string|null
     */
    protected $spec;

    /**
     * The queue collection.
     *
     * @var Collection
     */
    protected $collection;

    /**
     * Current reserver type description.
     *
     * @var string
     */
    protected const TYPE_DESCRIPTION = 'undefined';

    /**
     * Instantiate a new reserver, given a list of queues that it should be working on.
     *
     * @param  Collection  $collection
     * @param  array|null  $queues
     * @param  string|null $spec
     * @param  string|null $worker
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        Collection $collection,
        $queues = null,
        ?string $spec = null,
        ?string $worker = null
    ) {
        if (empty($queues) == true && empty($spec) == true) {
            throw new InvalidArgumentException(
                'A queues list or a specification to reserve queues are required.'
            );
        }

        // Get the queues to reserve.
        if (empty($queues) == false) {
            $queues = is_array($queues) ? $queues : [$queues];
            $this->queues = array_map(function (string $name) use ($collection): Queue {
                return $collection[trim($name)];
            }, $queues);
        } else {
            $this->spec = $spec;
            $this->refreshQueues = true;
            $this->queues = $collection->fromSpec($spec);
        }

        $this->collection = $collection;
        $this->worker = $worker;
        $this->logger = new NullLogger();
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
     */
    public function beforeWork(): void
    {
        if ($this->refreshQueues && empty($this->spec) == false) {
            $this->queues = $this->collection->fromSpec($this->spec);

            if (empty($this->queues) == true) {
                $this->logger->info('Refreshing queues dynamically, but there are no queues yet');
            }
        }

        if (empty($this->queues) == false) {
            $this->logger->info(
                'Monitoring the following queues: {queues}',
                ['queues' => implode(', ', $this->queues)]
            );
        }
    }

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

    /**
     * {@inheritdoc}
     *
     * @param  LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
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

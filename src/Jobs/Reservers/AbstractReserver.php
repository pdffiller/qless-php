<?php

namespace Qless\Jobs\Reservers;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Jobs\BaseJob;
use Qless\Jobs\Reservers\Options\ReserverOptionsInterface;
use Qless\Queues\Collection;
use Qless\Queues\Queue;

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
     * @var ReserverOptionsInterface
     */
    protected $options;

    /**
     * Current reserver type description.
     *
     * @var string
     */
    protected const TYPE_DESCRIPTION = 'undefined';

    /**
     * Instantiate a new reserver, given a list of queues that it should be working on.
     *
     * @param ReserverOptionsInterface $options
     */
    public function __construct(ReserverOptionsInterface $options)
    {
        /** @var Collection $collection */
        $collection = $options->getCollection();
        $queues = $options->getQueues();
        $this->options = $options;

        if (empty($queues) == true && empty($this->options->getSpec()) == true) {
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
            $this->refreshQueues = true;
            $this->queues = $collection->fromSpec($this->options->getSpec());
        }

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
        /** @var Collection $collection */
        $collection = $this->options->getCollection();

        if ($this->refreshQueues && empty($this->options->getSpec()) == false) {
            $this->queues = $collection->fromSpec($this->options->getSpec());

            if (empty($this->queues) == true) {
                $this->logger->info('Refreshing queues dynamically, but there are no queues yet');
            }
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

    /**
     * {@inheritdoc}
     *
     * @return BaseJob|null
     */
    public function reserve(): ?BaseJob
    {
        $this->beforeWork();

        $this->logger->debug('Attempting to reserve a job using {reserver} reserver', [
            'reserver' => $this->getDescription(),
        ]);

        foreach ($this->queues as $queue) {
            /** @var \Qless\Jobs\BaseJob|null $job */
            $job = $queue->pop($this->options->getWorker());
            if ($job !== null) {
                $this->logger->info('Found a job on {queue}', ['queue' => (string) $queue]);
                return $job;
            }
        }

        return null;
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

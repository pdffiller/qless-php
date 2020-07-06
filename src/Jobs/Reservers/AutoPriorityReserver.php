<?php

namespace Qless\Jobs\Reservers;

use Qless\Queues\Collection;
use Qless\Storage\AutoPrioritiesStorageContract;
use Phpml\Classification\KNearestNeighbors;

/**
 * Qless\Jobs\Reservers\OrderedReserver
 *
 * @package Qless\Jobs\Reservers
 */
class AutoPriorityReserver extends PriorityReserver
{
    /**
     * {@inheritdoc}
     *
     * @var string
     */
    public const TYPE_DESCRIPTION = 'auto learn';

    /**
     * @var AutoPrioritiesStorageContract
     */
    protected $storage;

    protected $ai;

    public function __construct(
        Collection $collection,
        ?array $queues = null,
        ?string $spec = null,
        ?string $worker = null,
        ?AutoPrioritiesStorageContract $storage = null
    ) {
        parent::__construct($collection, $queues, $spec, $worker);

        $this->ai = new KNearestNeighbors();

        $this->storage = $storage;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function beforeWork(): void
    {
        $queuesData = [];

        $collectionsQeuesData = $this->storage->getQueuesData();

        if ($collectionsQeuesData) {
            $list = end($collectionsQeuesData);
            foreach ($list as $name => $item) {
                $this->priorities[$name] = $item['priority'];
            }
        }

        foreach ($this->queues as $queue) {
            $queuesData[(string)$queue] = [
                'priority' => $this->priorities[(string)$queue] ?? self::DEFAULT_PRIORITY,
                'length' => $queue->length(),
            ];
        }

        $this->storage->saveQueuesData($queuesData);

        $collectionsQeuesData = $this->storage->getQueuesData();

        for($index = 0; $index < count($collectionsQeuesData); $index++) {
            $sectionLength[$index] = array_sum(array_column($collectionsQeuesData[$index], 'length'));

            if (!$index) {
                continue;
            }

            foreach ($this->queues as $queue) {
                $priority = $collectionsQeuesData[$index][(string)$queue]['priority'];
                if ($sectionLength[$index - 1] > $sectionLength[$index]) {
                    $priority--; // #TODO try dont change
                } elseif ($sectionLength[$index - 1] < $sectionLength[$index]) {
                    $priority++;
                } else {
                    continue;
                }

                $this->ai->train([
                    [
                        $collectionsQeuesData[$index][(string)$queue]['priority'],
                        $sectionLength[$index],
                        $collectionsQeuesData[$index - 1][(string)$queue]['priority'],
                        $sectionLength[$index - 1],
                    ]
                ], [
                    $priority
                ]);
            }
        }

        $lastData = end($collectionsQeuesData);

        foreach ($this->queues as $queue) {
            $result = $this->ai->predict([[
                $this->priorities[(string) $queue],
                $queue->length(),
                $lastData[(string)$queue]['priority'],
                $lastData[(string)$queue]['length'],
            ]]);

            $priority = $result[0];

            $this->logger->info(
                'Queue {queue} priority: {priority}',
                ['queue' => (string) $queue, 'priority' => $priority]
            );

            $priority = max($priority, $this->minPriority);
            $priority = min($priority, $this->maxPriority);

            $this->priorities[(string)$queue] = $priority;
        }
    }

    public function setPrioritiesTtl(int $ttl = AutoPrioritiesStorageContract::TTL_DEFAULT): void {
        $this->storage->setTtl($ttl);
    }

    public function setStorage(AutoPrioritiesStorageContract $storage): void
    {
        $this->storage = $storage;
    }

    protected function getStorage(): AutoPrioritiesStorageContract
    {
        return $this->storage;
    }
}

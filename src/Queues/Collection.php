<?php

namespace Qless\Queues;

use ArrayAccess;
use Qless\Client;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\UnsupportedFeatureException;
use Qless\Support\PropertyAccessor;

/**
 * Qless\Queues\Collection
 *
 * @property-read array $counts
 *
 * @package Qless\Queues
 */
class Collection implements ArrayAccess
{
    use PropertyAccessor;

    /** @var Client */
    private $client;

    /**
     * Collection constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * What queues are there, and how many jobs do they have running, waiting, scheduled, etc.
     *
     * @return array
     */
    public function getCounts(): array
    {
        return json_decode($this->client->queues(), true) ?: [];
    }

    /**
     * Gets a list of existent Queues matched by specification (regular expression).
     *
     * @param  string $regexp
     * @return Queue[]
     */
    public function fromSpec(string $regexp): array
    {
        $response = [];

        if (empty($regexp)) {
            return $response;
        }

        $queues = json_decode($this->client->queues(), true) ?: [];

        foreach ($queues as $queue) {
            if (isset($queue['name']) && preg_match("/^$regexp$/", $queue['name'])) {
                $response[] = new Queue($queue['name'], $this->client);
            }
        }

        return $response;
    }

    /**
     * Gets a list of existent Queues matched by topic.
     *
     * @param  string $topic
     * @return Queue[]
     */
    public function fromSubscriptions(string $topic): array
    {
        $responses = [];

        if (empty($topic)) {
            return $responses;
        }

        $subscriptions = $this->client->call('subscription', 'default', 'all', $topic);
        $subscriptions = json_decode($subscriptions, true) ?: [];

        foreach ($subscriptions as $subscription => $queues) {
            $topicPattern = str_replace(['.', '*', '#'], ['\.', '[a-zA-z0-9^.]{1,}', '.*'], $subscription);
            if (preg_match("/^$topicPattern$/", $topic)) {
                $responses[] = $queues;
            }
        }

        return array_unique(array_merge([], ...$responses));
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        $queues = json_decode($this->client->queues(), true) ?: [];

        foreach ($queues as $queue) {
            if (isset($queue['name']) && $queue['name'] === $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a queue object associated with the provided queue name.
     *
     * @param  string $offset
     * @return Queue
     */
    public function offsetGet($offset): Queue
    {
        return new Queue($offset, $this->client);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetSet($offset, $value): void
    {
        throw new UnsupportedFeatureException('Setting a queue is not supported using Queues collection.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws QlessException If the queue is not empty.
     */
    public function offsetUnset($offset): void
    {
        $this[$offset]->forget();
    }
}

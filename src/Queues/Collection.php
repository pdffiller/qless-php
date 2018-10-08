<?php

namespace Qless\Queues;

use ArrayAccess;
use Qless\Client;
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
     * {@inheritdoc}
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
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
    public function offsetGet($offset)
    {
        return new Queue($offset, $this->client);
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetSet($offset, $value)
    {
        throw new UnsupportedFeatureException('Setting a queue is not supported using Queues collection.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetUnset($offset)
    {
        throw new UnsupportedFeatureException('Deleting a queue is not supported using Queues collection.');
    }
}

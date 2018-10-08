<?php

namespace  Qless\Workers;

use ArrayAccess;
use Qless\Client;
use Qless\Exceptions\UnsupportedFeatureException;
use Qless\Support\PropertyAccessor;

/**
 * Qless\Workers\Collection
 *
 * Collection for accessing worker information lazily.
 *
 * @property-read array $counts
 *
 * @package Qless\Workers
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
     * What workers are workers, and how many jobs are they running.
     *
     * @return array
     */
    public function getCounts(): array
    {
        return json_decode($this->client->workers(), true) ?: [];
    }

    /**
     * {@inheritdoc}
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        $workers = json_decode($this->client->workers(), true) ?: [];

        foreach ($workers as $worker) {
            if (isset($worker['name']) && $worker['name'] === $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $offset
     * @return array
     */
    public function offsetGet($offset)
    {
        $worker = json_decode($this->client->workers($offset), true) ?: [];

        $worker['jobs'] = $worker['jobs'] ?? [];
        $worker['stalled'] = $worker['stalled'] ?? [];


        return $worker;
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetSet($offset, $value)
    {
        throw new UnsupportedFeatureException('Setting a worker is not supported using Workers collection.');
    }

    /**
     * {@inheritdoc}
     *
     * @throws UnsupportedFeatureException
     */
    public function offsetUnset($offset)
    {
        throw new UnsupportedFeatureException('Deleting a worker is not supported using Workers collection.');
    }
}

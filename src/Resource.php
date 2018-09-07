<?php

namespace Qless;

/**
 * Qless\Resource
 *
 * @package Qless
 */
class Resource
{
    /** @var Client */
    private $client;

    /** @var string */
    private $name;

    /** @var int */
    private $max;

    /**
     * Resource constructor.
     *
     * @param Client $client
     * @param string $name
     */
    public function __construct(Client $client, string $name)
    {
        $this->client = $client;
        $this->name = $name;
    }

    /**
     * Returns true if this resource exists.
     *
     * @return bool
     *
     * @throws QlessException
     */
    public function exists(): bool
    {
        return $this->client->call('resource.exists', $this->name) == 1;
    }

    /**
     * Gets the current lock count for this resource.
     *
     * @return int
     *
     * @throws QlessException
     */
    public function getLockCount(): int
    {
        return (int) $this->client->call('resource.lock_count', $this->name);
    }

    /**
     * Get a list of job identifiers that have active locks for this resource.
     *
     * @return string[]
     *
     * @throws QlessException
     */
    public function getLocks(): array
    {
        $data = $this->client->call('resource.locks', $this->name);

        return $data ? json_decode($data, true) : [];
    }

    /**
     * Gets the current lock count for this resource.
     *
     * @return int
     *
     * @throws QlessException
     */
    public function getPendingCount(): int
    {
        return (int) $this->client->call('resource.pending_count', $this->name);
    }

    /**
     * Gets a list of job identifiers that are waiting on this resource.
     *
     * @return string[]
     *
     * @throws QlessException
     */
    public function getPending(): array
    {
        $data = $this->client->call('resource.pending', $this->name);

        return $data ? json_decode($data, true) : [];
    }

    /**
     * Deletes this resource.
     *
     * @return bool
     *
     * @throws QlessException
     */
    public function delete(): bool
    {
        return $this->client->call('resource.unset', $this->name) == 1;
    }

    /**
     * Update units available for this resource.
     *
     * @param  int $max
     * @return void
     *
     * @throws QlessException
     */
    public function setMax(int $max)
    {
        $this->client->call('resource.set', $this->name, $max);
        $this->max = null;
    }

    /**
     * Return the maximum number of units available.
     *
     * @return int
     *
     * @throws QlessException
     */
    public function getMax(): int
    {
        if ($this->max === null) {
            $this->max = (int) $this->client->call('resource.get', $this->name);
        }

        return $this->max;
    }
}

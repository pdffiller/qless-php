<?php

namespace Qless\Tests\Support;

use Redis;

/**
 * Qless\Tests\Support\RedisAwareTrait
 *
 * @package Qless\Tests
 */
trait RedisAwareTrait
{
    /** @var Redis */
    private $instance;

    /**
     * Gets the Redis instance.
     *
     * @param  bool $recreate
     * @return Redis
     */
    protected function redis(bool $recreate = false): Redis
    {
        if ($this->instance instanceof Redis == false || $recreate === true) {
            $config = $this->getRedisConfig();

            $this->instance = new Redis();
            $this->instance->connect($config['host'], $config['port'], $config['timeout']);
        }

        return $this->instance;
    }

    /**
     * Gets the Redis configuration.
     *
     * @param array $overrides
     * @return array
     */
    protected function getRedisConfig(array $overrides = []): array
    {
        return array_merge([
            'host'    => REDIS_HOST,
            'port'    => REDIS_PORT,
            'timeout' => REDIS_TIMEOUT,
        ], $overrides);
    }
}

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
     * @return Redis
     */
    protected function redis(): Redis
    {
        if ($this->instance instanceof Redis == false) {
            $this->instance = new Redis();
            call_user_func_array([$this->instance, 'connect'], $this->getRedisConfig());
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
            'port'    =>  REDIS_PORT,
            'timeout' => REDIS_TIMEOUT,
        ], $overrides);
    }
}

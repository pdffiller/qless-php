<?php

namespace Qless\Tests\Support;

use Predis\Client as Redis;

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

            $this->instance = new Redis(
                [
                    'scheme' => $config['scheme'],
                    'host'   => $config['host'],
                    'port'   => $config['port'],
                ]
            );

            $this->instance->connect();
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
            'host'   => REDIS_HOST,
            'port'   => REDIS_PORT,
            'scheme' => 'tcp',
        ], $overrides);
    }
}

<?php

namespace Qless;

use PHP_CodeSniffer\Tokenizers\PHP;
use Redis;

/**
 * Qless\Listener
 *
 * @package Qless
 */
class Listener
{
    /** @var Redis */
    private $redis;

    /** @var array */
    private $channels;

    /**
     * Listener constructor.
     *
     * @param array $config
     * @param array $channels
     */
    public function __construct(array $config, array $channels)
    {
        $this->redis = new Redis();
        $this->redis->connect(
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 6379,
            $config['timeout'] ?? 0.0
        );

        $this->channels  = $channels;
    }

    /**
     * Wait for messages.
     *
     * @param callable $callback
     * @return void
     */
    public function messages(callable $callback)
    {
        $this->redis->subscribe(
            $this->channels,
            function (Redis $redis, $channel, $data) use ($callback) {
                call_user_func($callback, $channel, $data ? json_decode($data) : null);
            }
        );
    }

    /**
     * Closes the Redis connection.
     *
     * @return void
     */
    public function stop()
    {
        $this->redis->close();
    }
}

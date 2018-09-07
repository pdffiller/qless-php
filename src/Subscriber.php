<?php

namespace Qless;

use Redis;

/**
 * Qless\Subscriber
 *
 * A class used for subscribing to messages in a thread.
 *
 * @package Qless
 */
class Subscriber
{
    /** @var Redis */
    private $redis;

    /** @var array */
    private $channels;

    /**
     * Subscriber constructor.
     *
     * @param Redis $redis
     * @param array $channels
     */
    public function __construct(Redis $redis, array $channels)
    {
        $this->redis = $redis;
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

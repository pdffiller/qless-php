<?php

namespace Qless\Subscribers;

use Qless\Events\QlessCoreEventFactory;
use Redis;
use Closure;

/**
 * Qless\Subscribers\QlessCoreSubscriber
 *
 * A class used for subscribing to messages in a thread.
 *
 * @package Qless\Events
 */
class QlessCoreSubscriber
{
    /** @var Redis */
    private $redis;

    /** @var array */
    private $channels = [];

    /**
     * Subscriber constructor.
     *
     * NOTE: use separate connections for pub and sub.
     * @link https://stackoverflow.com/questions/22668244/should-i-use-separate-connections-for-pub-and-sub-with-redis
     *
     * @param Closure $makeRedisConnection
     * @param array   $channels
     */
    public function __construct(Closure $makeRedisConnection, array $channels)
    {
        $redis = new Redis();
        $makeRedisConnection($redis);

        $this->redis = $redis;
        $this->channels = $channels;
    }

    /**
     * Wait for messages.
     *
     * @param  callable $callback
     * @return void
     */
    public function messages(callable $callback): void
    {
        $callback = function (Redis $redis, string $channel, ?string $data = null) use ($callback) {
            call_user_func($callback, $channel, QlessCoreEventFactory::fromData($data));
        };

        $this->redis->subscribe($this->channels, $callback);
    }

    /**
     * Closes the Redis connection.
     *
     * @return void
     */
    public function stop(): void
    {
        $this->redis->close();
    }
}

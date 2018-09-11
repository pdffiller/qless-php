<?php

namespace Qless\Events;

use Redis;

/**
 * Qless\Events\Subscriber
 *
 * A class used for subscribing to messages in a thread.
 *
 * @package Qless\Events
 */
class Subscriber
{
    /** @var Redis */
    private $redis;

    /** @var array */
    private $channels;

    /** @var EventsFactory */
    private $eventsFactory;

    /**
     * Subscriber constructor.
     *
     * @param Redis         $redis
     * @param array         $channels
     * @param EventsFactory $eventsFactory
     */
    public function __construct(Redis $redis, array $channels, EventsFactory $eventsFactory = null)
    {
        $this->redis = $redis;
        $this->channels  = $channels;
        $this->eventsFactory = $eventsFactory ?: new EventsFactory();
    }

    /**
     * Wait for messages.
     *
     * @param  callable $callback
     * @return void
     */
    public function messages(callable $callback)
    {
        $factory = $this->eventsFactory;
        $callback = function (Redis $redis, string $channel, ?string $data = null) use ($callback, $factory) {
            call_user_func($callback, $channel, $factory->fromData($data));
        };

        $this->redis->subscribe($this->channels, $callback);
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

<?php

namespace Qless;

class Listener
{

    private $redis;

    /**
     * @var array
     */
    private $channels;

    public function __construct($redisConfig, $channels) {
        $this->redis = new \Redis();
        $this->redis->connect($redisConfig['host'], $redisConfig['port']);

        $this->channels  = $channels;
    }

    /**
     * Wait for messages
     *
     * @param callable $callback
     */
    public function messages(callable $callback) {
        $this->redis->subscribe($this->channels, function (\Redis $redis, $channel, $data) use ($callback) {
            call_user_func($callback, $channel, json_decode($data));
        });
    }

    public function stop() {
        $this->redis->close();
    }
}
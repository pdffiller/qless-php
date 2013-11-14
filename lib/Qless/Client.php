<?php

namespace Qless;

require_once __DIR__ . '/Lua.php';
require_once __DIR__ . '/Config.php';

use Redis;


/**
 * Class Client
 * client to call lua scripts in qless-core for specific commands
 *
 * @package Qless
 *
 * @method string put() put(\string $worker, \string $queue, \string $job_identifier, \string $klass, array $data, \int $delay_in_seconds)
 * @method array pop() pop(\string $queue, \string $worker, \int $count)
 * @method int length() length(\string $queue)
 * @method int heartbeat() heartbeat()
 * @method int retry() retry(\string $jid, \string $queue, \string $worker, \int $delay = 0, \string $group, \string $message)
 * @method int cancel() cancel(\string $jid)
 * @method int fail() fail(\string $jid, \string $worker, \string $group, \string $message, \string $data = null)
 */
class Client
{

    /**
     * Used for testing and internal use
     *
     * @var Lua
     * @internal
     */
    public $lua;
    /**
     * @var Config
     */
    public $config;
    /**
     * @var array
     */
    private $redis = [];

    public function __construct($host = 'localhost', $port = 6379) {
        $this->redis['redis'] = new Redis();
        $this->redis['host']  = $host;
        $this->redis['port']  = $port;

        $this->lua    = new Lua($this->redis);
        $this->config = new Config($this);
    }

    /**
     * Used for testing
     *
     * @param string $luaClass
     *
     * @internal
     */
    public function setLuaClass($luaClass) {
        $this->lua = new $luaClass($this->redis);
    }

    public function __call($command, $arguments) {
        return $this->lua->run($command, $arguments);
    }

    public function getQueue($name) {
        return new Queue($name, $this);
    }

    /**
     * Call to reconnect to Redis server
     */
    public function reconnect() {
        $this->lua->reconnect();
    }
} 
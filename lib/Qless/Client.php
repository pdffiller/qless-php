<?php

namespace Qless;

require_once __DIR__ . '/Lua.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Resource.php';

use Redis;


/**
 * Class Client
 * client to call lua scripts in qless-core for specific commands
 *
 * @package Qless
 *
 * @method string put() put(\string $worker, \string $queue, \string $job_identifier, \string $klass, array $data, \int $delay_in_seconds)
 * @method string requeue() requeue(\string $worker, \string $queue, \string $job_identifier, \string $klass, array $data, \int $delay_in_seconds)
 * @method array pop() pop(\string $queue, \string $worker, \int $count)
 * @method int length() length(\string $queue)
 * @method int heartbeat() heartbeat()
 * @method int retry() retry(\string $jid, \string $queue, \string $worker, \int $delay = 0, \string $group, \string $message)
 * @method int cancel() cancel(\string $jid)
 * @method int fail() fail(\string $jid, \string $worker, \string $group, \string $message, \string $data = null)
 * @method string[] jobs(\string $state, \int $offset = 0, \int $count = 25)
 * @method string get(\string $jid)
 * @method string[] multiget(array $jids)
 * @method bool complete(\string $jid, \string $worker_name, \string $queue_name, array $data)
 * @method void timeout(\string $jid)
 * @method array failed(\string $group=false, \int $start=0, \int $limit=25)
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

    /**
     *
     * @param string $name name of queue
     *
     * @return Queue
     */
    public function getQueue($name) {
        return new Queue($this, $name);
    }

    public function getResource($name) {
        return new Resource($this, $name);
    }

    /**
     * Call to reconnect to Redis server
     */
    public function reconnect() {
        $this->lua->reconnect();
    }
} 
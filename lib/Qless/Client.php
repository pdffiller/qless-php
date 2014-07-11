<?php

namespace Qless;

require_once __DIR__ . '/Lua.php';
require_once __DIR__ . '/Config.php';
require_once __DIR__ . '/Resource.php';
require_once __DIR__ . '/Jobs.php';

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
 * @method array failed(\string $group = false, \int $start = 0, \int $limit = 25)
 * @method string[] tag(\string $op, $tags)
 *
 * @property-read Jobs jobs
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

    /**
     * @var Jobs
     */
    private $_jobs;

    public function __construct($host = 'localhost', $port = 6379) {
        $this->redis['redis'] = new Redis();
        $this->redis['host']  = $host;
        $this->redis['port']  = $port;

        $this->lua    = new Lua($this->redis);
        $this->config = new Config($this);
        $this->_jobs   = new Jobs($this);
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

    function __call($command, $arguments) {
        return $this->lua->run($command, $arguments);
    }

    function __get($prop) {
        if ($prop === 'jobs') return $this->_jobs;

        return null;
    }

    /**
     * Call a specific q-less command
     *
     * @param string $command
     * @param mixed  $arguments...
     *
     * @return mixed
     */
    public function call($command, $arguments) {
        $arguments = func_get_args();
        array_shift($arguments);
        return $this->lua->run($command, $arguments);
    }

    /**
     * Returns
     * @param string $name name of queue
     *
     * @return Queue
     */
    public function getQueue($name) {
        return new Queue($this, $name);
    }

    /**
     * APIs for manipulating a resource
     *
     * @param $name
     *
     * @return Resource
     */
    public function getResource($name) {
        return new Resource($this, $name);
    }

    /**
     * Returns APIs for querying information about jobs
     *
     * @return Jobs
     */
    public function getJobs() {
        return $this->_jobs;
    }

    /**
     * Call to reconnect to Redis server
     */
    public function reconnect() {
        $this->lua->reconnect();
    }
} 
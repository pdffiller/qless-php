<?php

namespace Qless;

/**
 * Qless\Client
 *
 * Client to call lua scripts in qless-core for specific commands.
 *
 * @package Qless
 *
 * @method string put(string $worker, string $queue, string $jid, string $klass, array $data, int $delay)
 * @method string requeue(string $worker, string $queue, string $jid, string $klass, array $data, int $delay)
 * @method array pop(string $queue, string $worker, int $count)
 * @method int length(string $queue)
 * @method int heartbeat()
 * @method int retry(string $jid, string $queue, string $worker, int $delay = 0, string $group, string $message)
 * @method int cancel(string $jid)
 * @method int unrecur(string $jid)
 * @method int fail(string $jid, string $worker, string $group, string $message, string $data = null)
 * @method string[] jobs(string $state, int $offset = 0, int $count = 25)
 * @method string get(string $jid)
 * @method string[] multiget(array $jids)
 * @method bool complete(string $jid, string $workerName, string $queueName, array $data)
 * @method void timeout(string $jid)
 * @method array failed(string $group = false, int $start = 0, int $limit = 25)
 * @method string[] tag(string $op, $tags)
 *
 * @property-read Jobs jobs
 */
class Client
{
    /**
     * Used for testing and internal use.
     *
     * @var Lua
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
    private $jobs;

    public function __construct($host = 'localhost', $port = 6379)
    {
        $this->redis['host']  = $host;
        $this->redis['port']  = $port;

        $this->lua    = new Lua($this->redis);
        $this->config = new Config($this);
        $this->jobs   = new Jobs($this);
    }

    /**
     * Return the host for the Redis server
     *
     * @return string
     */
    public function getRedisHost()
    {
        return $this->redis['host'];
    }

    /**
     * Return the port for the Redis server
     *
     * @return int
     */
    public function getRedisPort()
    {
        return $this->redis['port'];
    }

    /**
     * Create a new listener
     *
     * @param $channels
     *
     * @return Listener
     */
    public function createListener($channels)
    {
        return new Listener($this->redis, $channels);
    }

    /**
     * Used for testing
     *
     * @param string $luaClass
     *
     * @internal
     */
    public function setLuaClass($luaClass)
    {
        $this->lua = new $luaClass($this->redis);
    }

    /**
     * @param string $klass     The class with the 'performMethod' specified in the data.
     * @param string $jid       The specified job id, if false is specified, a jid will be generated.
     * @param mixed  $data      An array of parameters for job.
     * @param int    $interval  The recurring interval in seconds.
     * @param int    $offset    A delay before the first run in seconds.
     * @param int    $retries   Number of times the job can retry when it runs.
     * @param int    $priority  A negative priority will run sooner.
     * @param array  $resources An array of resource identifiers this job must acquire before being processed.
     * @param array  $tags      An array of tags to add to the job.
     * @param mixed  $params    Additional parameters.
     *
     * @return string
     */
    public function recur(
        string $klass,
        string $jid,
        array $data,
        int $interval,
        int $offset,
        int $retries,
        int $priority,
        array $resources,
        array $tags,
        ...$params
    ) {
        return $this->lua->run('recur', func_get_args());
    }

    public function __call($command, $arguments)
    {
        return $this->lua->run($command, $arguments);
    }

    public function __get($prop)
    {
        if ($prop === 'jobs') {
            return $this->jobs;
        }

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
    public function call($command, $arguments)
    {
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
    public function getQueue($name)
    {
        return new Queue($name, $this);
    }

    /**
     * APIs for manipulating a resource
     *
     * @param $name
     *
     * @return Resource
     */
    public function getResource($name)
    {
        return new Resource($this, $name);
    }

    /**
     * Returns APIs for querying information about jobs
     *
     * @return Jobs
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Call to reconnect to Redis server
     */
    public function reconnect()
    {
        $this->lua->reconnect();
    }
}

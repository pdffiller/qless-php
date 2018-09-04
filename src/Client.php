<?php

namespace Qless;

use Qless\Resource as QResource;

/**
 * Qless\Client
 *
 * Client to call lua scripts in qless-core for specific commands.
 *
 * @package Qless
 *
 * @method string put(string $worker, string $queue, string $jid, string $klass, array $data, int $delay)
 * @method string requeue(string $worker, string $queue, string $jid, string $klass, array $data, int $delay)
 * @method int length(string $queue)
 * @method int heartbeat()
 * @method int retry(string $jid, string $queue, string $worker, int $delay = 0, string $group, string $message)
 * @method int cancel(string $jid)
 * @method int unrecur(string $jid)
 * @method int fail(string $jid, string $worker, string $group, string $message, string $data = null)
 * @method string[] jobs(string $state, int $offset = 0, int $count = 25)
 * @method string get(string $jid)
 * @method string[] multiget(string[] $jid)
 * @method bool complete(string $jid, string $workerName, string $queueName, array $data)
 * @method void timeout(string $jid)
 * @method array failed(string $group = false, int $start = 0, int $limit = 25)
 * @method string[] tag(string $op, $tags)
 *
 * @property-read Jobs jobs
 * @property-read Config config
 * @property-read Lua lua
 */
class Client
{
    /** @var Lua */
    private $lua;

    /** @var Config */
    private $config;

    /** @var array */
    private $redis = [];

    /** @var Jobs */
    private $jobs;

    /**
     * Client constructor.
     *
     * @param string $host    Can be a host, or the path to a unix domain socket.
     * @param int    $port    The redis port [optional].
     * @param float  $timeout Value in seconds (optional, default is 0.0 meaning unlimited).
     */
    public function __construct(string $host = '127.0.0.1', int $port = 6379, float $timeout = 0.0)
    {
        $this->redis['host']  = $host;
        $this->redis['port']  = $port;
        $this->redis['timeout']  = $timeout;

        $this->lua    = new Lua($this->redis);
        $this->config = new Config($this);
        $this->jobs   = new Jobs($this);
    }

    /**
     * Return the host for the Redis server
     *
     * @return string
     */
    public function getRedisHost(): string
    {
        return $this->redis['host'];
    }

    /**
     * Return the port for the Redis server
     *
     * @return int
     */
    public function getRedisPort(): int
    {
        return $this->redis['port'];
    }

    /**
     * Return the redis timeout.
     *
     * @return float
     */
    public function getRedisTimeout(): float
    {
        return $this->redis['port'];
    }

    /**
     * Create a new listener.
     *
     * @param $channels
     *
     * @return Listener
     */
    public function createListener($channels): Listener
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
     *
     * @throws QlessException
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

    /**
     * Get the next job on the desired queue.
     *
     * @param string $queue
     * @param string $worker
     * @param int $count
     * @return string|null
     *
     * @throws QlessException
     */
    public function pop(string $queue, string $worker, int $count)
    {
        $result = $this->lua->run('pop', [$queue, $worker, $count]);

        return $result;
    }

    /**
     * Call a specific q-less command.
     *
     * @param string $command
     * @param $arguments
     * @return mixed
     *
     * @throws QlessException
     */
    public function __call(string $command, array $arguments)
    {
        return $this->lua->run($command, $arguments);
    }

    /**
     * Getting inaccessible properties.
     *
     * @param string $prop
     * @return null|Config|Jobs|Lua
     */
    public function __get($prop)
    {
        switch ($prop) {
            case 'jobs':
                return $this->jobs;
            case 'config':
                return $this->config;
            case 'lua':
                return $this->lua;
            default:
                return null;
        }
    }

    /**
     * Call a specific q-less command.
     *
     * @param string $command
     * @param mixed $arguments
     * @return mixed|null
     *
     * @throws QlessException
     */
    public function call(string $command, ...$arguments)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->__call($command, $arguments);
    }

    /**
     * Creates a Queue instance.
     *
     * @param string $name The name of a queue.
     * @return Queue
     */
    public function getQueue($name): Queue
    {
        return new Queue($name, $this);
    }


    /**
     * Creates a Resource instance.
     *
     * @param string $name The name of a resource.
     * @return QResource
     */
    public function getResource(string $name): QResource
    {
        return new QResource($this, $name);
    }

    /**
     * Returns APIs for querying information about jobs.
     *
     * @return Jobs
     */
    public function getJobs(): Jobs
    {
        return $this->jobs;
    }

    /**
     * Call to reconnect to Redis server.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->lua->reconnect();
    }
}

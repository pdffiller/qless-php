<?php

namespace Qless;

use Qless\Exceptions\ExceptionInterface;
use Redis;

/**
 * Qless\Client
 *
 * Client to call lua scripts in qless-core for specific commands.
 *
 * @package Qless
 *
 * @method string put(string $worker, string $queue, string $jid, string $klass, string $data, int $delay, ...$args)
 * @method string recur(string $queue, string $jid, string $klass, string $data, string $spec, ...$args)
 * @method string requeue(string $worker, string $queue, string $jid, string $klass, string $data, int $delay, ...$args)
 * @method string pop(string $queue, string $worker, int $count)
 * @method int length(string $queue)
 * @method int heartbeat(...$args)
 * @method int retry(string $jid, string $queue, string $worker, int $delay, string $group, string $message)
 * @method int cancel(string $jid)
 * @method int unrecur(string $jid)
 * @method int fail(string $jid, string $worker, string $group, string $message, string $data = null)
 * @method string[] jobs(string $state, int $offset = 0, int $count = 25)
 * @method string get(string $jid)
 * @method string multiget(string[] $jid)
 * @method bool complete(string $jid, string $workerName, string $queueName, array $data)
 * @method void timeout(string $jid)
 * @method string failed(string $group = false, int $start = 0, int $limit = 25)
 * @method string[] tag(string $op, $tags)
 *
 * @property-read Jobs $jobs
 * @property-read Config $config
 * @property-read LuaScript $lua
 */
class Client
{
    /** @var LuaScript */
    private $lua;

    /** @var Config */
    private $config;

    /** @var Jobs */
    private $jobs;

    /** @var Redis */
    private $redis;

    /** @var string */
    protected $redisHost;

    /** @var int */
    protected $redisPort = 6379;

    /** @var float */
    protected $redisTimeout = 0.0;

    /**
     * Client constructor.
     *
     * @param string $host    Can be a host, or the path to a unix domain socket.
     * @param int    $port    The redis port [optional].
     * @param float  $timeout Value in seconds (optional, default is 0.0 meaning unlimited).
     */
    public function __construct(string $host = '127.0.0.1', int $port = 6379, float $timeout = 0.0)
    {
        $this->redisHost = $host;
        $this->redisPort = $port;
        $this->redisTimeout = $timeout;

        $this->redis = new Redis();
        $this->connect();

        $this->lua    = new LuaScript($this->redis);
        $this->config = new Config($this);
        $this->jobs   = new Jobs($this);
    }

    /**
     * Factory method to create a new Subscriber instance.
     *
     * @param  array $channels An array of channels to subscribe to.
     * @return Subscriber
     */
    public function createSubscriber(array $channels): Subscriber
    {
        return new Subscriber($this->redis, $channels);
    }

    /**
     * Call a specific q-less command.
     *
     * @param string $command
     * @param mixed ...$arguments
     * @return mixed|null
     *
     * @throws ExceptionInterface
     */
    public function call(string $command, ...$arguments)
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->__call($command, $arguments);
    }

    /**
     * Call a specific q-less command.
     *
     * @param string $command
     * @param array $arguments
     * @return mixed
     *
     * @throws ExceptionInterface
     */
    public function __call(string $command, array $arguments)
    {
        return $this->lua->run($command, $arguments);
    }

    /**
     * Gets the inaccessible, internal properties.
     *
     * @param  string $prop
     * @return Config|Jobs|LuaScript|Redis|null
     */
    public function __get(string $prop)
    {
        switch ($prop) {
            case 'jobs':
                return $this->jobs;
            case 'config':
                return $this->config;
            case 'lua':
                return $this->lua;
            case 'redis':
                return $this->redis;
            default:
                return null;
        }
    }

    /**
     * Removes all the entries from the default Redis database.
     *
     * @return void
     */
    public function flush()
    {
        $this->redis->flushDB();
    }

    /**
     * Call to reconnect to Redis server.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->redis->close();
        $this->connect();
    }

    /**
     * Perform connection to the Redis server.
     *
     * @return void
     */
    private function connect()
    {
        $this->redis->connect($this->redisHost, $this->redisPort, $this->redisTimeout);
    }
}

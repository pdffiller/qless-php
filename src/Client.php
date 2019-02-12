<?php

namespace Qless;

use Qless\Exceptions\QlessException;
use Qless\Jobs\Collection as JobsCollection;
use Qless\Queues\Collection as QueuesCollection;
use Qless\Subscribers\WatchdogSubscriber;
use Qless\Support\PropertyAccessor;
use Qless\Workers\Collection as WorkersCollection;
use Predis\Client as Redis;

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
 * @method string popByJid(string $queue, string $jid, string $worker)
 * @method int length(string $queue)
 * @method float heartbeat(...$args)
 * @method int retry(string $jid, string $queue, string $worker, int $delay, string $group, string $message)
 * @method array cancel(string $jid)
 * @method int unrecur(string $jid)
 * @method bool|string fail(string $jid, string $worker, string $group, string $message, ?string $data = null)
 * @method string[] jobs(string $state, int $offset = 0, int $count = 25)
 * @method null|string get(string $jid)
 * @method string multiget(string[] $jid)
 * @method string complete(string $jid, string $workerName, string $queueName, string $data, ...$args)
 * @method void timeout(string $jid)
 * @method string failed(string|bool $group = false, int $start = 0, int $limit = 25)
 * @method string[] tag(string $op, $tags)
 * @method string stats(string $queueName, int $date)
 * @method void pause(string $queueName, ...$args)
 * @method string queues(?string $queueName = null)
 * @method void unpause(string $queueName, ...$args)
 * @method string workers(?string $workerName = null)
 * @method string workerJobs(string $worker)
 * @method string subscription(string $queue, $op, $topic)
 *
 * @property-read JobsCollection $jobs
 * @property-read WorkersCollection $workers
 * @property-read QueuesCollection $queues
 * @property-read Config $config
 * @property-read LuaScript $lua
 */
class Client implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait, PropertyAccessor;

    /** @var LuaScript */
    private $lua;

    /** @var Config */
    private $config;

    /** @var JobsCollection */
    private $jobs;

    /** @var WorkersCollection */
    private $workers;

    /** @var QueuesCollection */
    private $queues;

    /** @var Redis */
    private $redis;

    /** @var string */
    private $workerName;

    /**
     * Client constructor.
     *
     * @param  mixed $parameters Connection parameters for one or more servers.
     * @param  mixed $options    Options to configure some behaviours of the client.
     *
     * @throws \Qless\Exceptions\InvalidArgumentException
     * @throws \Qless\Exceptions\RedisConnectionException
     */
    public function __construct($parameters = null, $options = null)
    {
        $this->workerName = php_uname('n') . '-' . getmypid();

        $this->redis = new Redis($parameters, $options);
        $this->redis->connect();

        $this->setEventsManager(new EventsManager());

        $this->lua = new LuaScript($this->redis);
        $this->config = new Config($this);
        $this->jobs = new JobsCollection($this);
        $this->workers = new WorkersCollection($this);
        $this->queues = new QueuesCollection($this);
    }

    /**
     * Gets internal worker name.
     *
     * @return string
     */
    public function getWorkerName(): string
    {
        return $this->workerName;
    }

    /**
     * Factory method to create a new Subscriber instance.
     *
     * NOTE: use separate connections for pub and sub.
     * @link https://stackoverflow.com/questions/22668244/should-i-use-separate-connections-for-pub-and-sub-with-redis
     *
     * @param  array $channels An array of channels to subscribe to.
     * @return WatchdogSubscriber
     *
     * @throws \Qless\Exceptions\RedisConnectionException
     */
    public function createSubscriber(array $channels): WatchdogSubscriber
    {
        $redis = clone $this->redis;

        $redis->disconnect();
        $redis->connect();

        return new WatchdogSubscriber($redis, $channels);
    }

    /**
     * Call a specific q-less command.
     *
     * @param  string $command
     * @param  mixed  ...$arguments
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
     * Call a specific q-less command.
     *
     * @param  string $command
     * @param  array $arguments
     * @return mixed
     *
     * @throws QlessException
     */
    public function __call(string $command, array $arguments)
    {
        return $this->lua->run($command, $arguments);
    }

    /**
     * Gets the jobs collection.
     *
     * @return JobsCollection
     */
    public function getJobs(): JobsCollection
    {
        return $this->jobs;
    }

    /**
     * Gets the workers collection.
     *
     * @return WorkersCollection
     */
    public function getWorkers(): WorkersCollection
    {
        return $this->workers;
    }

    /**
     * Gets the queues collection.
     *
     * @return QueuesCollection
     */
    public function getQueues(): QueuesCollection
    {
        return $this->queues;
    }

    /**
     * Gets the qless config.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Gets the wrapper to load and execute Lua script for qless-core.
     *
     * @return LuaScript
     */
    public function getLua(): LuaScript
    {
        return $this->lua;
    }

    /**
     * Removes all the entries from the default Redis database.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->redis->flushdb();
    }

    /**
     * Call to reconnect to Redis server.
     *
     * @return void
     */
    public function reconnect(): void
    {
        $this->redis->disconnect();
        $this->redis->connect();
    }
}

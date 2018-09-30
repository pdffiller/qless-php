<?php

namespace Qless;

use Qless\Exceptions\QlessException;
use Qless\Exceptions\UnknownPropertyException;
use Qless\Jobs\Collection as JobsCollection;
use Qless\Queues\Collection as QueuesCollection;
use Qless\Subscribers\QlessCoreSubscriber;
use Qless\Workers\Collection as WorkersCollection;

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
 * @method float heartbeat(...$args)
 * @method int retry(string $jid, string $queue, string $worker, int $delay, string $group, string $message)
 * @method array cancel(string $jid)
 * @method int unrecur(string $jid)
 * @method bool|string fail(string $jid, string $worker, string $group, string $message, ?string $data = null)
 * @method string[] jobs(string $state, int $offset = 0, int $count = 25)
 * @method false|string get(string $jid)
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
 *
 * @property-read JobsCollection $jobs
 * @property-read WorkersCollection $workers
 * @property-read QueuesCollection $queues
 * @property-read Config $config
 * @property-read LuaScript $lua
 */
class Client implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

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
     * @param  string   $server   Host/port combination separated by a colon, DSN-formatted URI.
     * @param  int|null $database Redis database (optional, default is 0).
     *
     * @throws \Qless\Exceptions\InvalidArgumentException
     * @throws \Qless\Exceptions\RedisConnectionException
     */
    public function __construct(string $server, ?int $database = null)
    {
        $this->workerName = gethostname() . '-' . getmypid();

        $this->redis = new Redis($server, $database);
        $this->redis->connect();

        $this->setEventsManager(new EventsManager());

        $this->lua = new LuaScript($this->redis->getDriver());
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
     * @param  array $channels An array of channels to subscribe to.
     * @return QlessCoreSubscriber
     *
     * @throws \Qless\Exceptions\RedisConnectionException
     */
    public function createSubscriber(array $channels): QlessCoreSubscriber
    {
        $redis = clone $this->redis;

        $redis->getDriver()->close();
        $redis->connect();

        return new QlessCoreSubscriber($redis->getDriver(), $channels);
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
     * Gets the internal Client's properties.
     *
     * @param  string $name
     * @return mixed
     *
     * @throws \Qless\Exceptions\UnknownPropertyException
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'jobs':
                return $this->jobs;
            case 'workers':
                return $this->workers;
            case 'queues':
                return $this->queues;
            case 'config':
                return $this->config;
            case 'lua':
                return $this->lua;
            default:
                throw new UnknownPropertyException('Getting unknown property: ' . self::class . '::' . $name);
        }
    }

    /**
     * Removes all the entries from the default Redis database.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->redis->getDriver()->flushDB();
    }

    /**
     * Call to reconnect to Redis server.
     *
     * @return void
     *
     * @throws \Qless\Exceptions\RedisConnectionException
     */
    public function reconnect(): void
    {
        $this->redis->getDriver()->close();
        $this->redis->connect();
    }
}

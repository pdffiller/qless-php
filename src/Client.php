<?php

namespace Qless;

use Qless\Exceptions\ExceptionInterface;
use Qless\Exceptions\UnknownPropertyException;
use Qless\Jobs\Collection as JobsCollection;
use Qless\Subscribers\QlessCoreSubscriber;
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
 * @method bool|string fail(string $jid, string $worker, string $group, string $message, string $data = null)
 * @method string[] jobs(string $state, int $offset = 0, int $count = 25)
 * @method bool|string get(string $jid)
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

    /** @var Redis */
    private $redis;

    /** @var string */
    private $redisHost;

    /** @var int */
    private $redisPort = 6379;

    /** @var float */
    private $redisTimeout = 0.0;

    /** @var string */
    private $workerName;

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
        $this->workerName = gethostname() . '-' . getmypid();

        $this->redis = new Redis();
        $this->connect();

        $this->setEventsManager(new EventsManager());

        $this->lua = new LuaScript($this->redis);
        $this->config = new Config($this);

        $this->jobs = new JobsCollection($this);
        $this->jobs->setEventsManager($this->getEventsManager());
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
     */
    public function createSubscriber(array $channels): QlessCoreSubscriber
    {
        return new QlessCoreSubscriber(
            function (Redis $redis) {
                $redis->connect($this->redisHost, $this->redisPort, $this->redisTimeout);
            },
            $channels
        );
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
     * Gets the internal Client's properties.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $job->property;`.
     *
     * @param  string $name
     * @return mixed
     *
     * @throws UnknownPropertyException
     */
    public function __get(string $name)
    {
        switch ($name) {
            case 'jobs':
                $collection = $this->jobs;
                $collection->setEventsManager($this->getEventsManager());

                return $collection;
            case 'config':
                return $this->config;
            case 'lua':
                return $this->lua;
            case 'redis':
                return $this->redis;
            default:
                throw new UnknownPropertyException('Getting unknown property: ' . self::class . '::' . $name);
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

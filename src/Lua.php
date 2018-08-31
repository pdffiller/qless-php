<?php

namespace Qless;

/**
 * Qless\Lua
 *
 * Wrapper to load and execute lua script for qless-core.
 *
 * @package Qless
 */
class Lua
{
    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $redisHost;

    /**
     * @var int
     */
    protected $redisPort = 6379;

    /**
     * Redis connection timeout (default is 0.0 meaning unlimited).
     *
     * @var float
     */
    protected $redisTimeout = 0.0;

    /**
     * @var ?string
     */
    protected $sha = null;

    /**
     * Lua constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->redisHost = $config['host'];
        $this->redisPort = $config['port'] ?? 6379;
        $this->redisTimeout = $config['timeout'] ?? 0.0;

        $this->redis = new \Redis();
        $this->connect();
    }

    /**
     * Perform connection to the Redis.
     *
     * @return void
     */
    protected function connect()
    {
        $this->redis->connect($this->redisHost, $this->redisPort, $this->redisTimeout);
    }

    /**
     * Run a Lua command serverside.
     *
     * @param string $command
     * @param array $args
     * @return mixed|null
     *
     * @throws QlessException
     */
    public function run($command, array $args)
    {
        if (empty($this->sha)) {
            $this->reload();
        }

        $luaArgs  = [$command, microtime(true)];
        $argArray = array_merge($luaArgs, $args);

        $result = $this->redis->evalSha($this->sha, $argArray);
        $error  = $this->redis->getLastError();

        if ($error !== null) {
            $this->handleError($error);
            return null;
        }

        return $result;
    }

    /**
     * Parse a Lua error and throw human readable exception.
     *
     * @param string $error
     * @return void
     *
     * @throws QlessException
     */
    protected function handleError($error)
    {
        $this->redis->clearLastError();

        throw QlessException::createExceptionFromError($error);
    }

    /**
     * Reloads the qless-core code.
     *
     * @return void
     */
    protected function reload()
    {
        $script = file_get_contents(__DIR__ . '/qless-core/qless.lua', true);
        $this->sha = sha1($script);

        $res = $this->redis->script('exists', $this->sha);

        if ($res[0] !== 1) {
            $this->sha = $this->redis->script('load', $script);
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
     * Reconnect to the Redis server.
     *
     * @return void
     */
    public function reconnect()
    {
        $this->redis->close();
        $this->connect();
    }
}

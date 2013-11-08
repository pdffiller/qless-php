<?php

namespace Qless;

require_once __DIR__ . '/QlessException.php';

/**
 * Class Lua
 * wrapper to load and execute lua script for qless-core.
 *
 * @package Qless
 */
class Lua
{

    /**
     * @var \Redis
     */
    private $redisCli;
    private $redisHost;
    private $redisPort;
    private $sha = null;

    public function __construct($redis) {
        $this->redisCli  = $redis['redis'];
        $this->redisHost = $redis['host'];
        $this->redisPort = $redis['port'];
    }

    public function run($command, $args) {
        if (empty($this->sha)) {
            $this->reload();
        }
        $luaArgs  = [$command, time()];
        $argArray = array_merge($luaArgs, $args);
        $this->redisCli->connect($this->redisHost, $this->redisPort);
        $result = $this->redisCli->evalSha($this->sha, $argArray);
        $error  = $this->redisCli->getLastError();
        if ($error) {
            throw QlessException::createException($error);
        }
        $this->redisCli->close();

        return $result;
    }

    private function reload() {
        $script    = file_get_contents(__DIR__ . '/qless-core/qless.lua', true);
        $this->sha = sha1($script);
        $this->redisCli->connect($this->redisHost, $this->redisPort);
        $res = $this->redisCli->script('exists', $this->sha);
        if ($res[0] !== 1) {
            $this->sha = $this->redisCli->script('load', $script);
        }
        $this->redisCli->close();
    }

    /**
     * Removes all the entries from the default Redis database
     */
    public function flush() {
        $this->redisCli->connect($this->redisHost, $this->redisPort);
        $this->redisCli->flushDB();
        $this->redisCli->close();
    }

} 
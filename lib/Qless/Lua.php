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
    /**
     * @var string
     */
    private $redisHost;
    /**
     * @var int
     */
    private $redisPort;
    /**
     * @var string
     */
    private $sha = null;

    public function __construct($redis) {
        $this->redisCli  = $redis['redis'];
        $this->redisHost = $redis['host'];
        $this->redisPort = $redis['port'];

        $this->redisCli->connect($this->redisHost, $this->redisPort);
    }

    function __destruct() {
        $this->redisCli->close();
    }

    public function run($command, $args) {
        if (empty($this->sha)) {
            $this->reload();
        }
        $luaArgs  = [$command, microtime(true)];
        $argArray = array_merge($luaArgs, $args);
        $result = $this->redisCli->evalSha($this->sha, $argArray);
        $error  = $this->redisCli->getLastError();
        if ($error) {
            $this->redisCli->clearLastError();
            throw QlessException::createException($error);
        }

        return $result;
    }

    private function reload() {
        $script    = file_get_contents(__DIR__ . '/qless-core/qless.lua', true);
        $this->sha = sha1($script);
        $res = $this->redisCli->script('exists', $this->sha);
        if ($res[0] !== 1) {
            $this->sha = $this->redisCli->script('load', $script);
        }
    }

    /**
     * Removes all the entries from the default Redis database
     *
     * @internal
     */
    public function flush() {
        $this->redisCli->flushDB();
    }

} 
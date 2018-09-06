<?php

namespace Qless;

use Redis;

/**
 * Qless\LuaScript
 *
 * Wrapper to load and execute Lua script for qless-core.
 * This is a class for internal usage only. You should not rely on its API
 * and to communicate with Lua you have to use Client class.
 *
 * @package Qless
 */
class LuaScript
{
    /** @var Redis */
    private $redis;

    /** @var string|null */
    private $sha;

    /**
     * Lua constructor.
     *
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Run a Lua command serverside.
     *
     * @param  string $command
     * @param  array  $args
     * @return mixed|null
     *
     * @throws QlessException
     */
    public function run($command, array $args)
    {
        if ($this->sha === null) {
            $this->reload();
        }

        $result = $this->redis->evalSha(
            $this->sha,
            $this->normalizeCommandArgs($command, $args)
        );

        $error = $this->redis->getLastError();

        if ($error !== null) {
            $this->handleError($error);
        }

        return $result;
    }

    /**
     * Prepares arguments to call the specified Lua command.
     *
     * @param  string $command
     * @param  array  $args
     * @return array
     */
    private function normalizeCommandArgs(string $command, array $args)
    {
        return array_merge([$command, microtime(true)], $args);
    }

    /**
     * Parse a Lua error and throw human readable exception.
     *
     * @param  string $error
     * @return void
     *
     * @throws QlessException
     */
    private function handleError(string $error)
    {
        $this->redis->clearLastError();

        throw QlessException::createExceptionFromError($error);
    }

    /**
     * Reloads the qless-core code.
     *
     * @return void
     */
    private function reload()
    {
        $script = file_get_contents(__DIR__ . '/qless-core/qless.lua', true);
        $this->sha = sha1($script);

        $res = $this->redis->script('exists', $this->sha);

        if ($res[0] !== 1) {
            $this->sha = $this->redis->script('load', $script) || null;
        }
    }
}

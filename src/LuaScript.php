<?php

namespace Qless;

use Qless\Exceptions\ExceptionFactory;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Predis\Client as Redis;

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

    /** @var ?string */
    private $sha;

    /** @var string */
    private $corePath;

    /**
     * Lua constructor.
     *
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
        $this->corePath = __DIR__ . '/qless-core/qless.lua';
    }

    /**
     * Run a Lua command serverside.
     *
     * @param  string $command
     * @param  array  $args
     * @return mixed|null
     *
     * @throws RuntimeException
     * @throws QlessException
     */
    public function run(string $command, array $args)
    {
        if (empty($this->sha)) {
            $this->reload();
        }

        $arguments = $this->normalizeCommandArgs($command, $args);

        try {
            return call_user_func_array([$this->redis, 'evalSha'], $arguments);
        } catch (\Exception $exception) {
            throw ExceptionFactory::fromErrorMessage($exception->getMessage());
        }
    }

    /**
     * Prepares arguments to call the specified Lua command.
     *
     * @param  string $command
     * @param  array  $args
     * @return array
     */
    private function normalizeCommandArgs(string $command, array $args): array
    {
        $arguments = array_merge([$command, microtime(true)], $args);

        array_unshift($arguments, 0);
        array_unshift($arguments, $this->sha);

        return $arguments;
    }

    /**
     * Reloads the qless-core code.
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function reload(): void
    {
        $this->sha = (string) @sha1_file($this->corePath);

        if (empty($this->sha)) {
            throw new RuntimeException(
                'Unable to locate qless-core file at path: ' . $this->corePath
            );
        }

        $res = $this->redis->script('exists', $this->sha);

        if ($res[0] !== 1) {
            $this->sha = $this->redis->script('load', file_get_contents($this->corePath));
        }
    }
}

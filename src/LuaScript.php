<?php

namespace Qless;

use Qless\Exceptions\ExceptionFactory;
use Qless\Exceptions\ExceptionInterface;
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

    /** @var ?string */
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
     * @throws ExceptionInterface
     */
    public function run(string $command, array $args)
    {
        if (empty($this->sha)) {
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
    private function normalizeCommandArgs(string $command, array $args): array
    {
        return array_merge([$command, microtime(true)], $args);
    }

    /**
     * Parse a Lua error and throw human readable exception.
     *
     * @param  string $error
     * @return void
     *
     * @throws ExceptionInterface
     */
    private function handleError(string $error): void
    {
        $this->redis->clearLastError();

        throw ExceptionFactory::fromErrorMessage($error);
    }

    /**
     * Reloads the qless-core code.
     *
     * @return void
     */
    private function reload(): void
    {
        $file = __DIR__ . '/qless-core/qless.lua';
        $this->sha = sha1_file($file);

        $res = $this->redis->script('exists', $this->sha);

        if ($res[0] !== 1) {
            $this->sha = $this->redis->script('load', file_get_contents($file));
        }
    }
}

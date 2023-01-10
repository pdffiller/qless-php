<?php

namespace Qless;

use Qless\Exceptions\ExceptionFactory;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\RuntimeException;
use Predis\Client as Redis;
use Qless\Exceptions\UnsupportedMethodException;

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
    private const BASE_SCRIPT = 'base';
    private const LIGHT_SCRIPT = 'light';
    private const SCRIPT_PATHS = [
        self::BASE_SCRIPT => __DIR__ . '/qless-core/qless.lua',
        self::LIGHT_SCRIPT => __DIR__ . '/qless-core/qless-light.lua',
    ];

    /** @var array */
    private $scriptHashes = [];

    /** @var string */
    private $activeScript;

    /** @var Redis */
    private $redis;

    /**
     * Lua constructor.
     *
     * @param Redis $redis
     */
    public function __construct(Redis $redis)
    {
        $this->redis = $redis;

        $this->loadScripts();
        $this->useBaseScript();
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
     * @throws UnsupportedMethodException
     */
    public function run(string $command, array $args)
    {
        $arguments = $this->normalizeCommandArgs($command, $args);

        try {
            return call_user_func_array([$this->redis, 'evalsha'], $arguments);

        } catch (\Exception $exception) {
            throw ExceptionFactory::fromErrorMessage($exception->getMessage());
        }
    }

    /**
     * Use base Lua script as core script
     *
     * @return void
     */
    public function useBaseScript(): void
    {
        $this->activeScript = self::BASE_SCRIPT;
    }

    /**
     * Use light Lua script as core script
     *
     * @return void
     */
    public function useLightScript(): void
    {
        $this->activeScript = self::LIGHT_SCRIPT;
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
        array_unshift($arguments, $this->scriptHashes[$this->activeScript]);

        return $arguments;
    }

    /**
     * Load all scripts
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private function loadScripts()
    {
        foreach (self::SCRIPT_PATHS as $name => $scriptPath) {
            $hash = (string) @sha1_file($scriptPath);

            if (empty($hash)) {
                throw new RuntimeException(
                    'Unable to locate qless-core file at path: ' . $scriptPath
                );
            }

            $result = $this->redis->script('exists', $hash);

            if ($result[0] !== 1) {
                $hash = $this->redis->script('load', file_get_contents($scriptPath));
            }

            $this->scriptHashes[$name] = $hash;
        }
    }
}

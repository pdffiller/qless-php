<?php

namespace Qless;

/**
 * Qless\Config
 *
 * @package Qless
 */
class Config
{
    /**
     * @var Client
     */
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Gets the value for the specified config name, falling back to the default if it does not exist
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed
     */
    public function get($name, $default = null)
    {
        $res = $this->client->lua->run('config.get', [$name]);

        return $res === false ? $default : $res;
    }

    /**
     * Sets the config name to the specified value
     *
     * @param string          $name
     * @param string|int|bool $value
     */
    public function set($name, $value)
    {
        $this->client->lua->run('config.set', [$name, $value]);
    }

    /**
     * Clears the specified config name
     *
     * @param string $name
     */
    public function clear($name)
    {
        $this->client->lua->run('config.unset', [$name]);
    }
}

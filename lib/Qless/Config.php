<?php

namespace Qless;

require_once __DIR__ . '/Client.php';

class Config {

    /**
     * @var Client
     */
    private $client;

    function __construct(Client $client) {
        $this->client = $client;
    }


    function get($name) {
        return $this->client->lua->run('config.get', [$name]);
    }

    function set($name, $value) {
        return $this->client->lua->run('config.set', [$name, $value]);
    }

    function clear($name) {
        return $this->client->lua->run('config.unset', [$name]);
    }
} 
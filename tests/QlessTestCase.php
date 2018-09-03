<?php

namespace Qless\Tests;

use PHPUnit\Framework\TestCase;
use Qless\Client;
use Qless\Tests\Support\RedisAwareTrait;

/**
 * Qless\Tests\QlessTestCase
 *
 * Base class for qless-php testing
 *
 * @package Qless\Tests
 */
abstract class QlessTestCase extends TestCase
{
    use RedisAwareTrait;

    /**  @var Client */
    protected $client;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setUp()
    {
        $callback = function (string $host, int $port, float $timeout) {
            return new Client($host, $port, $timeout);
        };

        $this->client = call_user_func_array($callback, $this->config());
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function tearDown()
    {
        $this->client->lua->flush();
    }
}

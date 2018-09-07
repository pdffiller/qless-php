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
        $config = $this->getRedisConfig();
        $this->client = new Client($config['host'], $config['port'], $config['timeout']);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function tearDown()
    {
        $this->client->flush();
    }
}

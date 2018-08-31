<?php

namespace Qless\Tests;

use Qless\Client;
use PHPUnit\Framework\TestCase;

/**
 * Qless\Tests\QlessTestCase
 *
 * Base class for qless-php testing
 *
 * @package Qless\Tests
 */
abstract class QlessTestCase extends TestCase
{
    /**  @var Client */
    protected $client;

    /** @var string */
    protected $redisHost;

    /** @var int */
    protected $redisPort;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setUp()
    {
        $this->redisHost = getenv('REDIS_HOST') ?: '127.0.0.1';
        $this->redisPort = getenv('REDIS_PORT') ?: 6379;

        $this->client = new Client(
            $this->redisHost,
            $this->redisPort
        );
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

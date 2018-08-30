<?php

namespace Qless\Tests;

use Qless\Client;
use PHPUnit\Framework\TestCase;

/**
 * Qless\Tests\QlessTest
 *
 * Base class for qless-php testing
 *
 * @package Qless\Tests
 */
abstract class QlessTestCase extends TestCase
{
    /**  @var Client */
    protected $client;

    public static $REDIS_HOST;
    public static $REDIS_PORT;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$REDIS_HOST = getenv('REDIS_HOST') ?: 'localhost';
        self::$REDIS_PORT = getenv('REDIS_PORT') ?: 6379;
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setUp()
    {
        $this->client = new Client(self::$REDIS_HOST, self::$REDIS_PORT);
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

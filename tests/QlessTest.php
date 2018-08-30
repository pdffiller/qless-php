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
class QlessTest extends TestCase
{
    public static $REDIS_HOST;
    public static $REDIS_PORT;

    public static function setUpBeforeClass()
    {
        self::$REDIS_HOST = getenv('REDIS_HOST') ?: 'localhost';
        self::$REDIS_PORT = getenv('REDIS_PORT') ?: 6379;
    }

    /**
     * @varClient
     */
    protected $client;

    public function setUp()
    {
        $this->client = new Client(self::$REDIS_HOST, self::$REDIS_PORT);
    }

    public function tearDown()
    {
        $this->client->lua->flush();
    }
}

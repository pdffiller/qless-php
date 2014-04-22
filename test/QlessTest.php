<?php

require_once __DIR__ . '/../lib/Qless/Client.php';
require_once __DIR__ . '/../lib/Qless/Queue.php';
require_once __DIR__ . '/../lib/Qless/Jobs.php';
require_once __DIR__ . '/LuaTester.php';

/**
 * Base class for qless-php testing
 */
class QlessTest extends PHPUnit_Framework_TestCase {

    static $REDIS_HOST;
    static $REDIS_PORT;

    public static function setUpBeforeClass() {
        self::$REDIS_HOST = getenv('REDIS_HOST') ?: 'localhost';
        self::$REDIS_PORT = getenv('REDIS_PORT') ?: 6379;
    }

    /**
     * @var Qless\Client
     */
    protected $client;

    public function setUp() {
        $this->client = new Qless\Client(self::$REDIS_HOST, self::$REDIS_PORT);
    }

    public function tearDown() {
        $this->client->lua->flush();
    }
}
 
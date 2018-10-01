<?php

namespace Qless\Tests;

use Qless\Redis;
use Qless\Tests\Support\RedisAwareTrait;

class RedisTest extends QlessTestCase
{
    use RedisAwareTrait;

    /**
     * @test
     * @expectedException \Qless\Exceptions\RedisConnectionException
     * @expectedExceptionMessageRegExp  '^Unable to connect to the Redis server: .*\.$'
     */
    public function shouldThrowExceptionWhenConnectToBogusAddress()
    {
        $redis = new Redis('redis://255.255.255.255:1234');
        $redis->connect();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\RedisConnectionException
     * @expectedExceptionMessage Unable to authenticate the Redis instance using provided password.
     */
    public function shouldThrowExceptionOnInvalidAuth()
    {
        $config = $this->getRedisConfig();

        $redis = new Redis("redis://foo:bar@{$config['host']}:{$config['port']}");
        $redis->connect();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\RedisConnectionException
     * @expectedExceptionMessage Unable to select the Redis database.
     */
    public function shouldThrowExceptionOnInvalidDatabase()
    {
        $config = $this->getRedisConfig();

        $redis = new Redis("redis://{$config['host']}:{$config['port']}/800");
        $redis->connect();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     */
    public function shouldThrowExceptionOnInvalidScheme()
    {
        $this->expectExceptionMessage(
            'Invalid Redis connection DSN. Supported schemes are: "redis://", "tcp://", "unix://", got "udp://".'
        );

        new Redis('udp://10.10.10.10');
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage The Redis connection DSN is empty.
     */
    public function shouldThrowExceptionOnEmptyDsn()
    {
        new Redis('');
    }

    /** @test */
    public function shouldUseSocketFormatAsAValidDsn()
    {
        $redis = new Redis('unix:///path/to/redis.sock');

        $reflection = new \ReflectionObject($redis);

        $host = $reflection->getProperty('host');
        $host->setAccessible(true);

        $this->assertSame('unix:///path/to/redis.sock', $host->getValue($redis));
    }
}

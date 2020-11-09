<?php

namespace Qless\Tests;

/**
 * Qless\Tests\ConfigTest
 *
 * @package Qless\Tests
 */
class ConfigTest extends QlessTestCase
{
    /**
     * @test
     */
    public function shouldGetDefaultHeartbeat(): void
    {
        self::assertEquals(60, $this->client->config->get('heartbeat'));
    }

    /**
     * @test
     */
    public function shouldSetConfigValue(): void
    {
        $key = md5(uniqid(microtime(true), true));
        $value = hash('sha1', uniqid(microtime(true), true));

        $this->client->config->set($key, $value);
        self::assertEquals($value, $this->client->config->get($key));
    }

    /**
     * @test
     */
    public function shouldGetDefaultValue(): void
    {
        self::assertEquals(null, $this->client->config->get('foo.bar.baz'));
        self::assertEquals('xyz', $this->client->config->get('foo.bar.baz', 'xyz'));
    }

    /**
     * @test
     */
    public function shouldGetDefaultGracePeriod(): void
    {
        $val = $this->client->config->get('grace-period');
        self::assertEquals(10, $val);
    }

    /**
     * @test
     */
    public function shouldSetHeartbeat(): void
    {
        $this->client->config->set('heartbeat', 10);
        $val = $this->client->config->get('heartbeat');
        self::assertEquals(10, $val);
    }

    /**
     * @test
     */
    public function shouldClearTheConfigValue(): void
    {
        $key = md5(uniqid(microtime(true), true));
        $value = hash('sha1', uniqid(microtime(true), true));

        $this->client->config->set($key, $value);
        $this->client->config->clear($key);

        self::assertNull($this->client->config->get($key));
    }
}

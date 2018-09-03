<?php

namespace Qless\Tests;

/**
 * Qless\Tests\ConfigTest
 *
 * @package Qless\Tests
 */
class ConfigTest extends QlessTestCase
{
    /** @test */
    public function shouldGetDefaultHeartbeat()
    {
        $this->assertEquals(60, $this->client->config->get('heartbeat'));
    }

    /** @test */
    public function shouldSetConfigValue()
    {
        $key = md5(uniqid(microtime(true), true));
        $value = hash('sha1', uniqid(microtime(true), true));

        $this->client->config->set($key, $value);
        $this->assertEquals($value, $this->client->config->get($key));
    }

    /** @test */
    public function shouldGetDefaultValue()
    {
        $this->assertEquals(null, $this->client->config->get('foo.bar.baz'));
        $this->assertEquals('xyz', $this->client->config->get('foo.bar.baz', 'xyz'));
    }

    /** @test */
    public function shouldGetDefaultGracePeriod()
    {
        $val = $this->client->config->get('grace-period');
        $this->assertEquals(10, $val);
    }

    /** @test */
    public function shouldSetHeartbeat()
    {
        $this->client->config->set('heartbeat', 10);
        $val = $this->client->config->get('heartbeat');
        $this->assertEquals(10, $val);
    }

    /** @test */
    public function shouldClearTheConfigValue()
    {
        $key = md5(uniqid(microtime(true), true));
        $value = hash('sha1', uniqid(microtime(true), true));

        $this->client->config->set($key, $value);
        $this->client->config->clear($key);

        $this->assertNull($this->client->config->get($key));
    }
}

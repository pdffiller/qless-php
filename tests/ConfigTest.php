<?php

namespace Qless\Tests;

/**
 * Qless\Tests\ConfigTest
 *
 * @package Qless\Tests
 */
class ConfigTest extends QlessTestCase
{
    public function testDefaultHeartbeat()
    {
        $val = $this->client->config->get('heartbeat');
        $this->assertEquals(60, $val);
    }

    public function testDefaultGracePeriod()
    {
        $val = $this->client->config->get('grace-period');
        $this->assertEquals(10, $val);
    }

    public function testSetHeartbeat()
    {
        $this->client->config->set('heartbeat', 10);
        $val = $this->client->config->get('heartbeat');
        $this->assertEquals(10, $val);
    }

    public function testItUsesDefaultValueWhenConfigNotSet()
    {
        $val = $this->client->config->get('__blah__', 'val');
        $this->assertEquals('val', $val);
    }

    public function testItDoesSetTheConfigValue()
    {
        $this->client->config->set('__blah__', 'val');
        $val = $this->client->config->get('__blah__');
        $this->assertEquals('val', $val);
    }

    public function testItDoesClearTheConfigValue()
    {
        $this->client->config->set('__blah__', 'val');
        $this->client->config->clear('__blah__');
        $val = $this->client->config->get('__blah__');
        $this->assertNull($val);
    }
}

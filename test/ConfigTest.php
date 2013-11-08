<?php

require_once __DIR__ . '/QlessTest.php';

class ConfigTest extends QlessTest {

    public function testDefaultHeartbeat() {
        $val = $this->client->config->get('heartbeat');
        $this->assertEquals(60, $val);
    }

    public function testDefaultGracePeriod() {
        $val = $this->client->config->get('grace-period');
        $this->assertEquals(10, $val);
    }

    public function testSetHeartbeat() {
        $this->client->config->set('heartbeat', 10);
        $val = $this->client->config->get('heartbeat');
        $this->assertEquals(10, $val);
    }


}
 
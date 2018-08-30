<?php

namespace Qless\Tests;

class ResourceTest extends QlessTest {

    public function testResourceDoesNotExist() {
        $r = $this->client->getResource('test-resource');

        $this->assertFalse($r->exists());
    }

    public function testDefaultMaxIsZero() {
        $r = $this->client->getResource('test-resource');

        $this->assertEquals(0, $r->getMax());
    }

    public function testCanSetMax() {
        $r = $this->client->getResource('test-resource');
        $r->setMax(10);
        $this->assertEquals(10, $r->getMax());
    }

    public function testCanSetAndResetMax() {
        $r = $this->client->getResource('test-resource');
        $r->setMax(10);
        $this->assertEquals(10, $r->getMax());
        $r->setMax(20);
        $this->assertEquals(20, $r->getMax());
    }

    public function testExistsAfterSettingMax() {
        $r = $this->client->getResource('test-resource');
        $this->assertFalse($r->exists());
        $r->setMax(10);
        $this->assertTrue($r->exists());
    }

    public function testCanDelete() {
        $r = $this->client->getResource('test-resource');
        $this->assertFalse($r->exists());
        $r->setMax(10);
        $this->assertTrue($r->exists());
        $res = $r->delete();
        $this->assertTrue($res);
        $this->assertFalse($r->exists());
    }

    public function testGetLockCountIsZeroForNewResource() {
        $r = $this->client->getResource('test-resource');
        $r->setMax(1);
        $c = $r->getLockCount();
        $this->assertEquals(0, $c);
    }

    public function testJobLocksResource() {
        $r = $this->client->getResource('r-1');
        $r->setMax(1);

        $this->put('j-1', ['r-1']);

        $lc = $r->getLockCount();
        $this->assertEquals(1, $lc);
        $l = $r->getLocks();
        $this->assertEquals(['j-1'], $l);

        $pc = $r->getPendingCount();
        $this->assertEquals(0, $pc);
    }

    public function testJobLocksResourceAndSecondIsPending() {
        $r = $this->client->getResource('r-1');
        $r->setMax(1);

        $this->put('j-1', ['r-1']);
        $this->put('j-2', ['r-1']);

        $lc = $r->getLockCount();
        $this->assertEquals(1, $lc);
        $l = $r->getLocks();
        $this->assertEquals(['j-1'], $l);

        $pc = $r->getPendingCount();
        $this->assertEquals(1, $pc);
        $p = $r->getPending();
        $this->assertEquals(['j-2'], $p);
    }

    private function put($jid, $res) {
        $res = json_encode($res, JSON_UNESCAPED_SLASHES);
        $this->client->put(null,
            'q-1',
            $jid,
            'k',
            json_encode(null, JSON_UNESCAPED_UNICODE),
            0,
            'resources', $res
        );
    }
}

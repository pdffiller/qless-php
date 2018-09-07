<?php

namespace Qless\Tests;

use Qless\Resource;

/**
 * Qless\Tests\ResourceTest
 *
 * @package Qless\Tests
 */
class ResourceTest extends QlessTestCase
{
    /** @test */
    public function shouldCorrectDetermineIfTheResourceDoesNotExist()
    {
        $resource = new Resource($this->client, 'test-resource-1');

        $this->assertFalse($resource->exists());
    }

    /** @test */
    public function shouldUseByDefaultZeroAsMaximumNumberOfUnits()
    {
        $resource = new Resource($this->client, 'test-resource-2');

        $this->assertEquals(0, $resource->getMax());
    }

    /** @test */
    public function shouldSetMaximumNumberOfUnits()
    {
        $resource = new Resource($this->client, 'test-resource-3');
        $resource->setMax(10);

        $this->assertEquals(10, $resource->getMax());
    }

    /** @test */
    public function shouldSetAndResetMaximumNumberOfUnits()
    {
        $resource = new Resource($this->client, 'test-resource-4');

        $resource->setMax(10);
        $this->assertEquals(10, $resource->getMax());

        $resource->setMax(20);
        $this->assertEquals(20, $resource->getMax());
    }

    /** @test */
    public function shouldFindResourceAfterSettingMaximumNumberOfUnits()
    {
        $resource = new Resource($this->client, 'test-resource-5');
        $this->assertFalse($resource->exists());

        $resource->setMax(10);
        $this->assertTrue($resource->exists());
    }

    /** @test */
    public function shouldDeleteResource()
    {
        $resource = new Resource($this->client, 'test-resource-6');

        $this->assertFalse($resource->exists());

        $resource->setMax(10);
        $this->assertTrue($resource->exists());

        $this->assertTrue($resource->delete());
        $this->assertFalse($resource->exists());
    }

    /** @test */
    public function shouldDoNothingWhenDeletingNonExistentResource()
    {
        $resource = new Resource($this->client, 'test-resource-7');

        $this->assertFalse($resource->exists());
        $this->assertFalse($resource->delete());
    }

    /** @test */
    public function shouldReceiveZeroForLockCountIfResourceIsNew()
    {
        $resource = new Resource($this->client, 'test-resource-8');

        $resource->setMax(1);
        $this->assertEquals(0, $resource->getLockCount());
    }

    /** @test */
    public function testJobLocksResource()
    {
        $resource = new Resource($this->client, 'test-resource-9');
        $resource->setMax(1);

        $this->put('test-resource-9-1', ['test-resource-9']);

        $this->assertEquals(1, $resource->getLockCount());
        $this->assertEquals(['test-resource-9-1'], $resource->getLocks());
        $this->assertEquals(0, $resource->getPendingCount());
    }

    /** @test */
    public function testJobLocksResourceAndSecondIsPending()
    {
        $resource = new Resource($this->client, 'test-resource-10');
        $resource->setMax(1);

        $this->put('test-resource-10-1', ['test-resource-10']);
        $this->put('test-resource-10-2', ['test-resource-10']);

        $this->assertEquals(1, $resource->getLockCount());
        $this->assertEquals(['test-resource-10-1'], $resource->getLocks());
        $this->assertEquals(1, $resource->getPendingCount());
        $this->assertEquals(['test-resource-10-2'], $resource->getPending());
    }

    private function put(string $jid, array $res)
    {
        $this->client->put(
            '',
            'q-1',
            $jid,
            'k',
            json_encode([], JSON_UNESCAPED_SLASHES),
            0,
            'resources',
            json_encode($res, JSON_UNESCAPED_SLASHES)
        );
    }
}

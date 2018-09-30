<?php

namespace Qless\Tests\Queues;

use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Queues\Collection;

/**
 * Qless\Tests\Queues\CollectionTest
 *
 * @package Qless\Tests\Queues
 */
class CollectionTest extends QlessTestCase
{
    /** @test */
    public function shouldGetQueuesList()
    {
        $collection = new Collection($this->client);
        $this->assertEquals([], $collection->counts);

        $this->client->put('w1', 'test-queue', 'j1', 'klass', '{}', 0);

        $expected = [
            [
                'paused'    => false,
                'running'   => 0,
                'name'      => 'test-queue',
                'waiting'   => 1,
                'recurring' => 0,
                'depends'   => 0,
                'stalled'   => 0,
                'scheduled' => 0,
            ]
        ];

        $this->assertEquals($expected, $collection->counts);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnknownPropertyException
     * @expectedExceptionMessage Getting unknown property: Qless\Queues\Collection::foo
     */
    public function shouldThrowExceptionWhenGetInaccessibleProperty()
    {
        $collection = new Collection($this->client);
        $collection->foo;
    }

    /** @test */
    public function shouldCheckWhetherAOffsetExists()
    {
        $collection = new Collection($this->client);

        $this->assertFalse(isset($collection['foo']));
        $this->assertFalse(isset($collection['bar']));

        $this->client->put('w1', 'foo', 'j1', 'klass', '{}', 0);

        $this->assertTrue(isset($collection['foo']));
        $this->assertFalse(isset($collection['bar']));
    }

    /** @test */
    public function shouldGetQueues()
    {
        $collection = new Collection($this->client);

        $this->assertInstanceOf(Queue::class, $collection['q1']);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnsupportedFeatureException
     * @expectedExceptionMessage Deleting a queue is not supported using Queues collection.
     */
    public function shouldThrowExceptionOnDeletingProperty()
    {
        $collection = new Collection($this->client);
        unset($collection['foo']);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnsupportedFeatureException
     * @expectedExceptionMessage Setting a queue is not supported using Queues collection.
     */
    public function shouldThrowExceptionOnSettingProperty()
    {
        $collection = new Collection($this->client);
        $collection['foo'] = 'bar';
    }
}

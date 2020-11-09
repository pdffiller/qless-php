<?php

namespace Qless\Tests\Queues;

use Qless\Exceptions\QlessException;
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
     */
    public function shouldThrowExceptionOnDeletingPropertyWhenNotEmpty(): void
    {
        $this->expectException(QlessException::class);
        $this->expectExceptionMessage('Queue is not empty');
        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];

        $collection = new Collection($this->client);
        $collection['foo']->put('Xxx\Yyy', $data, "jid-1");
        unset($collection['foo']);
    }

    /**
     * @test
     */
    public function shouldRemoveEmptyQueueOnDeletingProperty(): void
    {
        $data = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];

        $collection = new Collection($this->client);
        $queue = $collection['test-queue'];
        $queue->put('Xxx\Yyy', $data, "jid-1");
        self::assertTrue(isset($collection['test-queue']));
        $queue->cancel("jid-1");
        unset($collection['test-queue']);
        self::assertFalse(isset($this->client->queues['test-queue']));
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

    /** @test */
    public function shouldGetQueuesListBySpecification()
    {
        $pattern = 'eu-(west|east)-\d+';
        $collection = new Collection($this->client);

        $this->assertEquals([], $collection->fromSpec($pattern));

        $this->client->put('w1', 'foo', 'j1', 'klass', '{}', 0);
        $this->assertEquals([], $collection->fromSpec($pattern));

        $this->client->put('w1', 'eu-west-2', 'j1', 'klass', '{}', 0);
        $queues = $collection->fromSpec($pattern);

        $this->assertTrue(is_array($queues));
        $this->assertInstanceOf(Queue::class, $queues[0]);
        $this->assertCount(1, $queues);
        $this->assertEquals('eu-west-2', (string) $queues[0]);

        $this->client->put('w1', 'eu-east-1', 'j1', 'klass', '{}', 0);
        $queues = $collection->fromSpec($pattern);

        $this->assertCount(2, $queues);
        $this->assertEquals('eu-west-2', (string) $queues[0]);
        $this->assertEquals('eu-east-1', (string) $queues[1]);

        $this->assertEquals([], $collection->fromSpec(''));
    }
}

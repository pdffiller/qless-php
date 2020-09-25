<?php

namespace Qless\Tests\Queues;

use Qless\Exceptions\QlessException;
use Qless\Exceptions\UnknownPropertyException;
use Qless\Exceptions\UnsupportedFeatureException;
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
    public function shouldGetQueuesList(): void
    {
        $collection = new Collection($this->client);
        self::assertEquals([], $collection->counts);

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

        self::assertEquals($expected, $collection->counts);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionWhenGetInaccessibleProperty(): void
    {
        $this->expectExceptionMessage("Getting unknown property: Qless\Queues\Collection::foo");
        $this->expectException(UnknownPropertyException::class);
        $collection = new Collection($this->client);
        /** @noinspection PhpUndefinedFieldInspection */
        $collection->foo;
    }

    /** @test */
    public function shouldCheckWhetherAOffsetExists(): void
    {
        $collection = new Collection($this->client);

        self::assertFalse(isset($collection['foo']));
        self::assertFalse(isset($collection['bar']));

        $this->client->put('w1', 'foo', 'j1', 'klass', '{}', 0);

        self::assertTrue(isset($collection['foo']));
        self::assertFalse(isset($collection['bar']));
    }

    /** @test */
    public function shouldGetQueues(): void
    {
        $collection = new Collection($this->client);

        self::assertInstanceOf(Queue::class, $collection['q1']);
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
     *
     *
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
        $this->expectExceptionMessage("Setting a queue is not supported using Queues collection.");
     */
    public function shouldThrowExceptionOnSettingProperty()
    {
        $this->expectException(UnsupportedFeatureException::class);
        $collection = new Collection($this->client);
        $collection['foo'] = 'bar';
    }

    /** @test */
    public function shouldGetQueuesListBySpecification(): void
    {
        $pattern = 'eu-(west|east)-\d+';
        $collection = new Collection($this->client);

        self::assertEquals([], $collection->fromSpec($pattern));

        $this->client->put('w1', 'foo', 'j1', 'klass', '{}', 0);
        self::assertEquals([], $collection->fromSpec($pattern));

        $this->client->put('w1', 'eu-west-2', 'j1', 'klass', '{}', 0);
        $queues = $collection->fromSpec($pattern);

        self::assertIsArray($queues);
        self::assertInstanceOf(Queue::class, $queues[0]);
        self::assertCount(1, $queues);
        self::assertEquals('eu-west-2', (string) $queues[0]);

        $this->client->put('w1', 'eu-east-1', 'j1', 'klass', '{}', 0);
        $queues = $collection->fromSpec($pattern);

        self::assertCount(2, $queues);
        self::assertEquals('eu-west-2', (string) $queues[0]);
        self::assertEquals('eu-east-1', (string) $queues[1]);

        self::assertEquals([], $collection->fromSpec(''));
    }
}

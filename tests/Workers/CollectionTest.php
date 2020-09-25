<?php

namespace Qless\Tests\Workers;

use Qless\Exceptions\UnknownPropertyException;
use Qless\Exceptions\UnsupportedFeatureException;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Workers\Collection;

/**
 * Qless\Tests\Workers\CollectionTest
 *
 * @package Qless\Tests\Workers
 */
class CollectionTest extends QlessTestCase
{
    /** @test */
    public function shouldGetWorkersList(): void
    {
        $collection = new Collection($this->client);

        self::assertEquals([], $collection->counts);
        $this->client->pop('test-queue', 'w1', 1);
        self::assertEquals([['stalled' => 0, 'name' => 'w1', 'jobs' => 0]], $collection->counts);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionWhenGetInaccessibleProperty(): void
    {
        $this->expectExceptionMessage("Getting unknown property: Qless\Workers\Collection::foo");
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

        $this->client->pop('test-queue', 'foo', 1);

        self::assertTrue(isset($collection['foo']));
        self::assertFalse(isset($collection['bar']));
    }

    /** @test */
    public function shouldGetWorker(): void
    {
        $collection = new Collection($this->client);

        self::assertEquals(['stalled' => [], 'jobs' => []], $collection['w1']);

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Sample', [], 'jid');
        $queue->pop('w1');

        self::assertEquals(['stalled' => [], 'jobs' => ['jid']], $collection['w1']);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionOnDeletingProperty(): void
    {
        $this->expectExceptionMessage("Deleting a worker is not supported using Workers collection.");
        $this->expectException(UnsupportedFeatureException::class);
        $collection = new Collection($this->client);
        unset($collection['foo']);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionOnSettingProperty(): void
    {
        $this->expectExceptionMessage("Setting a worker is not supported using Workers collection.");
        $this->expectException(UnsupportedFeatureException::class);
        $collection = new Collection($this->client);
        $collection['foo'] = 'bar';
    }

    /** @test */
    public function shouldRemoveWorker(): void
    {
        $collection = new Collection($this->client);

        self::assertEquals(['stalled' => [], 'jobs' => []], $collection['w1']);

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Sample', [], 'jid');
        $queue->pop('w1');

        self::assertTrue($collection->remove('w1'));
        self::assertEmpty($collection->counts);
    }

    /** @test */
    public function shouldGetWorkersCount(): void
    {
        $collection = new Collection($this->client);

        self::assertEquals(0, $collection->getCount());

        $this->startFourWorkers();

        self::assertEquals(4, $collection->getCount());
    }

    /** @test */
    public function shouldGetWorkersRange(): void
    {
        $collection = new Collection($this->client);

        self::assertEquals([], $collection->getRange(0, -1));

        $this->startFourWorkers();

        self::assertEquals(
            [
                ['stalled' => 0, 'name' => 'fourth-worker', 'jobs' => 1],
                ['stalled' => 0, 'name' => 'third-worker', 'jobs' => 1],
            ],
            $collection->getRange(0, 1)
        );

        self::assertEquals(
            [
                ['stalled' => 0, 'name' => 'second-worker', 'jobs' => 1],
                ['stalled' => 0, 'name' => 'first-worker', 'jobs' => 1],
            ],
            $collection->getRange(2, 3)
        );
    }

    private function startFourWorkers(): void
    {
        $queue = new Queue('first-queue', $this->client);
        $queue->put('First', [], 'jid1');
        $queue->pop('first-worker');

        $queue = new Queue('second-queue', $this->client);
        $queue->put('Second', [], 'jid2');
        $queue->pop('second-worker');

        $queue = new Queue('third-queue', $this->client);
        $queue->put('Third', [], 'jid3');
        $queue->pop('third-worker');

        $queue = new Queue('fourth-queue', $this->client);
        $queue->put('Fourth', [], 'jid4');
        $queue->pop('fourth-worker');
    }
}

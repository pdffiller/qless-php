<?php

namespace Qless\Tests\Workers;

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
    public function shouldGetWorkersList()
    {
        $collection = new Collection($this->client);

        $this->assertEquals([], $collection->counts);
        $this->client->pop('test-queue', 'w1', 1);
        $this->assertEquals([['stalled' => 0, 'name' => 'w1', 'jobs' => 0]], $collection->counts);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnknownPropertyException
     * @expectedExceptionMessage Getting unknown property: Qless\Workers\Collection::foo
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

        $this->client->pop('test-queue', 'foo', 1);

        $this->assertTrue(isset($collection['foo']));
        $this->assertFalse(isset($collection['bar']));
    }

    /** @test */
    public function shouldGetWorker()
    {
        $collection = new Collection($this->client);

        $this->assertEquals(['stalled' => [], 'jobs' => []], $collection['w1']);

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Sample', [], 'jid');
        $queue->pop('w1');

        $this->assertEquals(['stalled' => [], 'jobs' => ['jid']], $collection['w1']);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnsupportedFeatureException
     * @expectedExceptionMessage Deleting a worker is not supported using Workers collection.
     */
    public function shouldThrowExceptionOnDeletingProperty()
    {
        $collection = new Collection($this->client);
        unset($collection['foo']);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnsupportedFeatureException
     * @expectedExceptionMessage Setting a worker is not supported using Workers collection.
     */
    public function shouldThrowExceptionOnSettingProperty()
    {
        $collection = new Collection($this->client);
        $collection['foo'] = 'bar';
    }

    /** @test */
    public function shouldRemoveWorker()
    {
        $collection = new Collection($this->client);

        $this->assertEquals(['stalled' => [], 'jobs' => []], $collection['w1']);

        $queue = new Queue('test-queue', $this->client);
        $queue->put('Sample', [], 'jid');
        $queue->pop('w1');

        $this->assertTrue($collection->remove('w1'));
        $this->assertEmpty($collection->counts);
    }

    /** @test */
    public function shouldGetWorkersCount()
    {
        $collection = new Collection($this->client);

        $this->assertEquals(0, $collection->getCount());

        $this->startFourWorkers();

        $this->assertEquals(4, $collection->getCount());
    }

    /** @test */
    public function shouldGetWorkersRange()
    {
        $collection = new Collection($this->client);

        $this->assertEquals([], $collection->getRange(0, -1));

        $this->startFourWorkers();

        $this->assertEquals(
            [
                ['stalled' => 0, 'name' => 'fourth-worker', 'jobs' => 1],
                ['stalled' => 0, 'name' => 'third-worker', 'jobs' => 1],
            ],
            $collection->getRange(0, 1)
        );

        $this->assertEquals(
            [
                ['stalled' => 0, 'name' => 'second-worker', 'jobs' => 1],
                ['stalled' => 0, 'name' => 'first-worker', 'jobs' => 1],
            ],
            $collection->getRange(2, 3)
        );
    }

    private function startFourWorkers()
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

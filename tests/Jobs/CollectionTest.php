<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\UnsupportedFeatureException;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Jobs\CollectionTest
 *
 * @package Qless\Tests\Jobs
 */
class CollectionTest extends QlessTestCase
{
    /** @test */
    public function shouldGetTaggedJobs(): void
    {
        self::assertEquals([], $this->client->jobs->tagged('foo'));
        self::assertEquals([], $this->client->jobs->tagged('bar'));

        $this->client->queues['test-queue']->put('Foo', [], 'jid-1', null, null, null, ['foo', 'bar']);
        $this->client->queues['test-queue']->put('Foo', [], 'jid-2', null, null, null, ['bar', 'baz']);

        self::assertEquals(['jid-1', 'jid-2'], $this->client->jobs->tagged('bar'));

        $this->client->queues['test-queue']->put('Foo', [], 'jid-3', null, null, null, ['foo']);
        $this->client->queues['test-queue']->put('Foo', [], 'jid-4', null, null, null, ['foo']);
        $this->client->queues['test-queue']->put('Foo', [], 'jid-5', null, null, null, ['foo']);

        self::assertEquals(['jid-3', 'jid-4'], $this->client->jobs->tagged('foo', 1, 2));
    }

    /** @test */
    public function shouldGetTrackedJobs(): void
    {
        $this->client->queues['foo']->put('Foo', [], 'jid-1');
        $this->client->queues['foo']->put('Bar', [], 'jid-2');
        self::assertCount(0, $this->client->jobs->tracked());

        $this->client->jobs['jid-1']->track();
        self::assertCount(1, $this->client->jobs->tracked());
        $this->client->jobs['jid-2']->track();
        self::assertCount(2, $this->client->jobs->tracked());

        $this->client->jobs['jid-1']->untrack();
        self::assertCount(1, $this->client->jobs->tracked());
        $this->client->jobs['jid-2']->untrack();
        self::assertCount(0, $this->client->jobs->tracked());
    }

    /** @test */
    public function shouldReturnNullForInvalidJobID(): void
    {
        self::assertNull($this->client->jobs['xxx']);
        self::assertNull($this->client->jobs->get('xxx'));

        $this->client->queues['test-queue']->put('Foo', [], 'xxx');

        $this->assertIsJob($this->client->jobs['xxx']);
        $this->assertIsJob($this->client->jobs->get('xxx'));
    }

    /** @test */
    public function shouldDetectIfJobExist(): void
    {
        $jid = substr(md5(uniqid(microtime(true), true)), 0, 16);
        self::assertFalse($this->client->jobs->offsetExists($jid));

        $this->client->queues['test-queue']->put('Foo', [], $jid);
        self::assertTrue($this->client->jobs->offsetExists($jid));
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionOnDeletingProperty(): void
    {
        $this->expectExceptionMessage("Deleting a job is not supported using Jobs collection.");
        $this->expectException(UnsupportedFeatureException::class);
        unset($this->client->jobs['xxx']);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionOnSettingProperty(): void
    {
        $this->expectExceptionMessage("Setting a job is not supported using Jobs collection.");
        $this->expectException(UnsupportedFeatureException::class);
        $this->client->jobs->offsetSet('foo', 'bar');
    }

    public function testItReturnsExistingJob(): void
    {
        $this->put('j-1');
        $j = $this->client->jobs['j-1'];

        self::assertNotNull($j);
        self::assertEquals('j-1', $j->jid);
    }

    /** @test */
    public function shouldReturnExistingJobsKeyedByJobIdentifier(): void
    {
        self::assertEquals([], $this->client->jobs->multiget([]));
        self::assertEquals([], $this->client->jobs->multiget(['non-existent-1', 'non-existent-2']));

        $this->put('j-1');
        $this->put('j-2');

        $j = $this->client->jobs->multiget(['j-1', 'j-2']);

        self::assertCount(2, $j);
        self::assertArrayHasKey('j-1', $j);
        self::assertArrayHasKey('j-2', $j);
    }

    public function testItReturnsNoCompletedJobsWhenNoneExist(): void
    {
        $this->put('j-1');
        $this->put('j-2');

        $j = $this->client->jobs->completed();
        self::assertEmpty($j);
    }

    /** @test */
    public function shouldReturnCompletedJobs(): void
    {
        $this->put('j-1');
        $this->put('j-2');

        $q  = new Queue('q-1', $this->client);

        $q->pop()->complete();
        $q->pop()->complete();

        $j = $this->client->jobs->completed();
        sort($j);

        self::assertEquals(['j-1', 'j-2'], $j);
    }

    /** @test */
    public function shouldReturnFailedJobs(): void
    {
        $this->put('j-1');
        $this->put('j-2');
        $this->put('j-3');
        $this->put('j-4');

        $q  = new Queue('q-1', $this->client);

        $q->pop()->fail('system', 'msg');
        $q->pop()->fail('system', 'msg');
        $q->pop()->fail('system', 'msg');
        $q->pop()->fail('main', 'msg');

        $j = $this->client->jobs->failed();

        self::assertEquals(3, $j['system']);
        self::assertEquals(1, $j['main']);
    }

    /**
     * @test
     * @depends shouldReturnFailedJobs
     */
    public function shouldReturnFailedBySpecificGroup(): void
    {
        $this->put('j-1');
        $this->put('j-2');
        $this->put('j-3');
        $this->put('j-4');

        $q  = new Queue('q-1', $this->client);

        $q->pop()->fail('system', 'msg');
        $q->pop()->fail('system', 'msg');
        $q->pop()->fail('system', 'msg');
        $q->pop()->fail('main', 'msg');

        $j = $this->client->jobs->failedForGroup('system');

        self::assertCount(3, $j['jobs']);
    }

    public function testItReturnsRunningJob(): void
    {
        $this->put('j-1');
        $this->put('j-2');

        $q  = new Queue('q-1', $this->client);
        $q->pop();

        $j = $this->client->jobs['j-1'];
        self::assertNotNull($j);
    }

    public function testItReturnsWorkerJobs(): void
    {
        $this->client->put('w1', 'test-queue', 'job1', 'klass', '{}', 0);
        $this->client->put('w1', 'test-queue', 'job2', 'klass', '{}', 0);

        $this->client->pop('test-queue', 'w1', 2);

        $jobs = $this->client->jobs->fromWorker('w1');
        self::assertIsArray($jobs);
        self::assertCount(2, $jobs);
    }

    public function testItReturnsNoWorkerJobsOnEmptyWorker(): void
    {
        $this->put('j-1');

        $q  = new Queue('q-1', $this->client);
        $q->pop();

        $jobs = $this->client->jobs->fromWorker('');
        self::assertIsArray($jobs);
        self::assertCount(0, $jobs);
    }

    public function testItReturnsWorkerJobsByTimeFilter(): void
    {
        $this->client->put('w1', 'test-queue', 'job1', 'klass', '{}', 0);

        $this->client->pop('test-queue', 'w1', 1);

        $jobs = $this->client->jobs->fromWorker('w1', '1 hour');
        self::assertIsArray($jobs);
        self::assertCount(1, $jobs);
    }

    public function testItReturnsAllWorkerJobsByInvalidTimeFilter(): void
    {
        $this->client->put('w1', 'test-queue', 'job1', 'klass', '{}', 0);
        $this->client->put('w1', 'test-queue', 'job2', 'klass', '{}', 0);
        $this->client->put('w1', 'test-queue', 'job3', 'klass', '{}', 0);

        $this->client->pop('test-queue', 'w1', 3);

        $jobs = $this->client->jobs->fromWorker('w1', '<whoops!VERY_bad_Time#>');
        self::assertIsArray($jobs);
        self::assertCount(3, $jobs);
    }


    private function put($jid, $opts = []): void
    {
        $opts = array_merge([
            'data'     => [],
            'delay'    => 0,
            'tags'     => [],
            'priority' => 0,
            'retries'  => 0,
            'interval' => 0,
        ], $opts);

        $this->client->put(
            '',
            'q-1',
            $jid,
            'k',
            json_encode($opts['data'], JSON_UNESCAPED_SLASHES),
            $opts['delay'],
            'tags',
            json_encode($opts['tags'], JSON_UNESCAPED_SLASHES),
            'priority',
            $opts['priority'],
            'retries',
            $opts['retries'],
            'interval',
            $opts['interval']
        );
    }
}

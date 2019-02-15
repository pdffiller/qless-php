<?php

namespace Qless\Tests\Jobs;

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
    public function shouldGetTaggedJobs()
    {
        $this->assertEquals([], $this->client->jobs->tagged('foo'));
        $this->assertEquals([], $this->client->jobs->tagged('bar'));

        $this->client->queues['test-queue']->put('Foo', [], 'jid-1', null, null, null, ['foo', 'bar']);
        $this->client->queues['test-queue']->put('Foo', [], 'jid-2', null, null, null, ['bar', 'baz']);

        $this->assertEquals(['jid-1', 'jid-2'], $this->client->jobs->tagged('bar'));

        $this->client->queues['test-queue']->put('Foo', [], 'jid-3', null, null, null, ['foo']);
        $this->client->queues['test-queue']->put('Foo', [], 'jid-4', null, null, null, ['foo']);
        $this->client->queues['test-queue']->put('Foo', [], 'jid-5', null, null, null, ['foo']);

        $this->assertEquals(['jid-3', 'jid-4'], $this->client->jobs->tagged('foo', 1, 2));
    }

    /** @test */
    public function shouldReturnNullForInvalidJobID()
    {
        $this->assertNull($this->client->jobs['xxx']);
        $this->assertNull($this->client->jobs->get('xxx'));

        $this->client->queues['test-queue']->put('Foo', [], 'xxx');

        $this->assertIsJob($this->client->jobs['xxx']);
        $this->assertIsJob($this->client->jobs->get('xxx'));
    }

    /** @test */
    public function shouldDetectIfJobExist()
    {
        $jid = substr(md5(uniqid(microtime(true), true)), 0, 16);
        $this->assertFalse($this->client->jobs->offsetExists($jid));

        $this->client->queues['test-queue']->put('Foo', [], $jid);
        $this->assertTrue($this->client->jobs->offsetExists($jid));
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnsupportedFeatureException
     * @expectedExceptionMessage Deleting a job is not supported using Jobs collection.
     */
    public function shouldThrowExceptionOnDeletingProperty()
    {
        unset($this->client->jobs['xxx']);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnsupportedFeatureException
     * @expectedExceptionMessage Setting a job is not supported using Jobs collection.
     */
    public function shouldThrowExceptionOnSettingProperty()
    {
        $this->client->jobs->offsetSet('foo', 'bar');
    }

    public function testItReturnsExistingJob()
    {
        $this->put('j-1');
        $j = $this->client->jobs['j-1'];

        $this->assertNotNull($j);
        $this->assertEquals('j-1', $j->jid);
    }

    /** @test */
    public function shouldReturnExistingJobsKeyedByJobIdentifier()
    {
        $this->assertEquals([], $this->client->jobs->multiget([]));
        $this->assertEquals([], $this->client->jobs->multiget(['non-existent-1', 'non-existent-2']));

        $this->put('j-1');
        $this->put('j-2');

        $j = $this->client->jobs->multiget(['j-1', 'j-2']);

        $this->assertCount(2, $j);
        $this->assertArrayHasKey('j-1', $j);
        $this->assertArrayHasKey('j-2', $j);
    }

    public function testItReturnsNoCompletedJobsWhenNoneExist()
    {
        $this->put('j-1');
        $this->put('j-2');

        $j = $this->client->jobs->completed();
        $this->assertEmpty($j);
    }

    /** @test */
    public function shouldReturnCompletedJobs()
    {
        $this->put('j-1');
        $this->put('j-2');

        $q  = new Queue('q-1', $this->client);

        $q->pop()->complete();
        $q->pop()->complete();

        $j = $this->client->jobs->completed();
        sort($j);

        $this->assertEquals(['j-1', 'j-2'], $j);
    }

    /** @test */
    public function shouldReturnFailedJobs()
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

        $this->assertEquals(3, $j['system']);
        $this->assertEquals(1, $j['main']);
    }

    /**
     * @test
     * @depends shouldReturnFailedJobs
     */
    public function shouldReturnFailedBySpecificGroup()
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

        $this->assertCount(3, $j['jobs']);
    }

    public function testItReturnsRunningJob()
    {
        $this->put('j-1');
        $this->put('j-2');

        $q  = new Queue('q-1', $this->client);
        $q->pop();

        $j = $this->client->jobs['j-1'];
        $this->assertNotNull($j);
    }

    public function testItReturnsWorkerJobs()
    {
        $this->client->put('w1', 'test-queue', 'job1', 'klass', '{}', 0);
        $this->client->put('w1', 'test-queue', 'job2', 'klass', '{}', 0);

        $this->client->pop('test-queue', 'w1', 2);

        $jobs = $this->client->jobs->fromWorker('w1');
        $this->assertIsArray($jobs);
        $this->assertCount(2, $jobs);
    }

    public function testItReturnsNoWorkerJobsOnEmptyWorker()
    {
        $this->put('j-1');

        $q  = new Queue('q-1', $this->client);
        $q->pop();

        $jobs = $this->client->jobs->fromWorker('');
        $this->assertIsArray($jobs);
        $this->assertCount(0, $jobs);
    }

    public function testItReturnsWorkerJobsByTimeFilter()
    {
        $this->client->put('w1', 'test-queue', 'job1', 'klass', '{}', 0);

        $this->client->pop('test-queue', 'w1', 1);

        $jobs = $this->client->jobs->fromWorker('w1', '1 hour');
        $this->assertIsArray($jobs);
        $this->assertCount(1, $jobs);
    }

    public function testItReturnsAllWorkerJobsByInvalidTimeFilter()
    {
        $this->client->put('w1', 'test-queue', 'job1', 'klass', '{}', 0);
        $this->client->put('w1', 'test-queue', 'job2', 'klass', '{}', 0);
        $this->client->put('w1', 'test-queue', 'job3', 'klass', '{}', 0);

        $this->client->pop('test-queue', 'w1', 3);

        $jobs = $this->client->jobs->fromWorker('w1', '<whoops!VERY_bad_Time#>');
        $this->assertIsArray($jobs);
        $this->assertCount(3, $jobs);
    }


    private function put($jid, $opts = [])
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

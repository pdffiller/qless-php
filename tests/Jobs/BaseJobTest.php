<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\JobAlreadyFinishedException;
use Qless\Jobs\BaseJob;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;

/**
 * Qless\Tests\Jobs\BaseJobTest
 *
 * @package Qless\Tests\Jobs
 */
class BaseJobTest extends QlessTestCase
{
    /**
     * @test
     * @expectedException \Qless\Exceptions\UnknownPropertyException
     * @expectedExceptionMessage Setting unknown property: Qless\Jobs\BaseJob::foo
     */
    public function shouldThrowExceptionWhenSettingUnknownProperty()
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleHandler', [], 'jid');

        $job = $this->client->jobs['jid'];

        $job->foo = 'bar';
    }

    /** @test */
    public function shouldChangeJobPriority()
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleHandler', [], 'jid');

        $job = $this->client->jobs['jid'];
        $this->assertEquals(0, $job->priority);

        $job = $this->client->jobs['jid'];
        $job->priority = 18;

        $job = $this->client->jobs['jid'];

        $this->assertEquals(18, $job->priority);
    }

    /** @test */
    public function shouldChangeFailedFlag()
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleHandler', [], 'jid');

        $job = $this->client->jobs['jid'];
        $this->assertEquals(false, $job->failed);

        $job = $queue->pop();
        $this->assertEquals(false, $job->failed);

        $job->fail('test', 'current job is failed');
        $this->assertEquals(true, $job->failed);

        $job = $this->client->jobs['jid'];
        $this->assertEquals(true, $job->failed);
    }

    /** @test */
    public function shouldRequeueJob()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 1, ['tag1','tag2']);
        $queue->pop()->requeue();

        $job = $queue->pop();

        $this->assertEquals(1, $job->priority);
        $this->assertEquals(['tag1','tag2'], $job->tags);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage Could not find job class SampleJobPerformClass.
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentClass()
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put('SampleJobPerformClass', []);

        $job = $queue->pop();
        $queue->pop();

        $job->getInstance();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage Job class "stdClass" does not contain perform method "myPerformMethod".
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentPerformMethod()
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put('stdClass', ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']);

        $job = $queue->pop();
        $queue->pop();

        $job->getInstance();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithInvalidPerformMethod()
    {
        $this->expectExceptionMessage(
            'Job class "Qless\Tests\Stubs\JobHandler" does not contain perform method "myPerformMethod2".'
        );

        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(JobHandler::class, ['performMethod' => 'myPerformMethod2', 'payload' => 'otherData']);

        $job = $queue->pop();
        $queue->pop();

        $job->getInstance();
    }

    /** @test */
    public function shouldGetWorkerInstance()
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(JobHandler::class, ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']);

        $job = $queue->pop();
        $queue->pop();

        $this->assertInstanceOf(JobHandler::class, $job->getInstance());
    }

    /** @test */
    public function shouldGetWorkerInstanceWithDefaultPerformMethod()
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(JobHandler::class, []);

        $job = $queue->pop();
        $queue->pop();

        $this->assertInstanceOf(JobHandler::class, $job->getInstance());
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\LostLockException
     * @expectedExceptionMessageRegExp "Job .* given out to another worker: worker-2 "
     */
    public function shouldThrowIOnCallingHeartbeatForInvalidJob()
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put('SampleJobPerformClass', []);

        $job = $queue->pop('worker-1');
        $queue->pop('worker-2');

        $job->heartbeat();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\LostLockException
     * @expectedExceptionMessage Job jid not currently running: waiting
     */
    public function shouldThrowLostLockExceptionWhenHeartbeatFail()
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('Foo', [], 'jid');

        $this->client->jobs['jid']->heartbeat();
    }

    /** @test */
    public function shouldProvideAccessToHeartbeat()
    {
        $this->client->config->set('heartbeat', 10);
        $this->client->queues['foo']->put('Foo', [], 'jid');

        $job = $this->client->queues['foo']->pop();
        $before = $job->ttl();

        $this->client->config->set('heartbeat', 20);
        $job->heartbeat();

        $this->assertTrue($job->ttl() > $before);
    }

    /** @test */
    public function shouldStartTrackingJob()
    {
        $this->client->queues['foo']->put('Foo', [], 'jid');
        $this->assertFalse($this->client->jobs['jid']->tracked);

        $this->client->jobs['jid']->track();
        $this->assertTrue($this->client->jobs['jid']->tracked);

        $this->client->jobs['jid']->untrack();
        $this->assertFalse($this->client->jobs['jid']->tracked);
    }

    /** @test */
    public function shouldRemoveOldTrackingJob()
    {
        $lifetime = 1;
        $lifetimeOld = $this->client->config->get('jobs-history');
        $this->client->config->set('jobs-history', $lifetime);

        $jid = $this->client->queues['foo']->put('Foo', []);

        $this->client->jobs[$jid]->track();

        $this->assertTrue($this->client->jobs[$jid]->tracked);

        sleep($lifetime);

        $jid2 = $this->client->queues['foo']->put('Foo', []);

        $queue = $this->client->queues['foo'];

        $job = $queue->popByJid($jid2);

        $job->complete();

        $this->assertNotContains($jid, $this->client->getJobs()->tracked());

        $this->client->config->set('jobs-history', $lifetimeOld);
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\QlessException
     * @expectedExceptionMessage Job jid not running
     */
    public function shouldThrowExceptionWhenTimeoutFail()
    {
        $this->client->queues['foo']->put('Foo', [], 'jid');
        $this->client->jobs['jid']->timeout();
    }

    /** @test */
    public function shouldTimeoutJob()
    {
        $this->client->queues['foo']->put('Foo', [], 'jid');
        $job = $this->client->queues['foo']->pop();

        $job->timeout();
        unset($job);

        $job = $this->client->queues['foo']->pop();

        $this->assertEquals('timed-out', $job->history[2]['what']);
    }

    /** @test */
    public function shouldGetCorrectTtl()
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleJobPerformClass', []);

        $this->assertGreaterThan(55, $queue->pop()->ttl());
    }

    /** @test */
    public function shouldCompleteJob()
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleJobPerformClass', []);

        $this->assertEquals('complete', $queue->pop()->complete());
    }

    /** @test */
    public function shouldCompleteJobAndPutItInAnotherQueue()
    {
        $queue1 = $this->client->queues['test-queue-1'];
        $queue2 = $this->client->queues['test-queue-2'];

        $queue1->put('SampleJobPerformClass', ['size' => 2]);

        $this->assertEquals(1, $queue1->length());
        $this->assertEquals(0, $queue2->length());

        $job1 = $queue1->pop();
        $this->assertEquals(2, $job1->data['size']);

        $job1->data['size'] -= 1;

        $this->assertEquals('waiting', $job1->complete('test-queue-2'));

        $this->assertEquals(0, $queue1->length());
        $this->assertEquals(1, $queue2->length());

        $job2 = $queue2->pop();

        $this->assertInstanceOf(BaseJob::class, $job2);
        $this->assertEquals(1, $job2->data['size']);
    }

    /** @test */
    public function shouldNotPopFailedJob()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid');

        $this->assertEquals('jid', $queue->pop()->fail('account', 'failed to connect'));
        $this->assertNull($queue->pop('worker-1'));
    }

    public function testRetryDoesReturnJobAndDefaultsToFiveRetries()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid');

        $job1 = $queue->pop();
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(4, $remaining);

        $job1 = $queue->pop();
        $this->assertEquals('jid', $job1->jid);
    }

    public function testRetryDoesRespectRetryParameterWithOneRetry()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 1);

        $this->assertZero($queue->pop()->retry('account', 'failed to connect'));
        $this->assertEquals('jid', $queue->pop()->jid);
    }

    public function testRetryDoesReturnNegativeWhenNoMoreAvailable()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 0);

        $job1 = $queue->pop();
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(-1, $remaining);
    }

    public function testRetryTransitionsToFailedWhenExhaustedRetries()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 0);

        $job = $queue->pop();
        $job->retry('account', 'failed to connect');

        $this->assertNull($queue->pop());
    }

    /** @test */
    public function shouldCancelRemovesJob()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0);
        $queue->put('SampleJobPerformClass', [], 'jid-2', 0, 0);

        $this->assertEquals(['jid-1'], $queue->pop()->cancel());
    }

    /** @test */
    public function shouldCancelRemovesJobWithDependents()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', null, 0);
        $queue->put('SampleJobPerformClass', [], 'jid-2', null, 0, null, null, ['jid-1']);

        $this->assertEquals(['jid-1', 'jid-2'], $queue->pop()->cancel(true));
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\QlessException
     * @expectedExceptionMessage jid-1 is a dependency of jid-2 but is not mentioned to be canceled
     */
    public function shouldThrowExceptionOnCancelWithoutDependencies()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', null, 0);
        $queue->put('SampleJobPerformClass', [], 'jid-2', null, 0, null, null, ['jid-1']);

        $queue->pop()->cancel();
    }

    /** @test */
    public function shouldUnlockJobWhenDependenciesIsCompleted()
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put('MakeStuffing', ['lots' => 'of butter'], 'jid-1');
        $queue->put('MakeTurkey', ['with' => 'stuffing'], 'jid-2', null, null, null, null, [$jid]);

        $stuffingJob = $queue->pop();
        $turkeyJob = $queue->pop();

        $this->assertEquals(['jid-2'], $stuffingJob->dependents);
        $this->assertEquals('jid-1', $stuffingJob->jid);

        $this->assertNull($turkeyJob);

        $stuffingJob->complete();
        $turkeyJob = $queue->pop();

        $this->assertEquals('jid-2', $turkeyJob->jid);
    }

    /**
     * @test
     * @dataProvider jobPropertiesDataProvider
     *
     * @param string $property
     * @param string $type
     */
    public function shouldGetInternalProperties(string $property, string $type)
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', []);
        $job = $queue->pop();

        $this->assertEquals($type, gettype($job->{$property}));
    }

    public function jobPropertiesDataProvider()
    {
        return [
            ['jid', 'string'],
            ['klass', 'string'],
            ['queue', 'string'],
            ['data', 'object'],
            ['history', 'array'],
            ['dependencies', 'array'],
            ['dependents', 'array'],
            ['priority', 'integer'],
            ['worker', 'string'],
            ['tags', 'array'],
            ['expires', 'double'], // I ❤︎ PHP
            ['remaining', 'integer'],
            ['retries', 'integer'],
            ['tracked', 'boolean'],
            ['description', 'string'],
        ];
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\UnknownPropertyException
     * @expectedExceptionMessage Getting unknown property: Qless\Jobs\BaseJob::foo
     */
    public function shouldThrowExceptionWhenGetInaccessibleProperty()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', []);
        $job = $queue->pop();

        $job->foo;
    }

    /** @test */
    public function shouldTreatedLikeAString()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'job-id');
        $job = $queue->pop();

        $expected = 'Qless\Jobs\BaseJob SampleJobPerformClass job-id / test-queue';

        $this->assertEquals($expected, (string) $job);
        $this->assertEquals($expected, $job->__toString());
    }

    /** @test */
    public function shouldProvideAccessToTheDataUsingArrayNotation()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', ['foo' => 'bar'], 'job-id');
        $job = $queue->pop();

        $this->assertTrue(isset($job['foo']));
        $this->assertEquals('bar', $job['foo']);

        $job['baz'] = 'buz';

        $this->assertTrue(isset($job['baz']));
        $this->assertEquals('buz', $job['baz']);

        unset($job['baz']);

        $this->assertFalse(isset($job['baz']));
    }

    public function testItCanAddTagsToAJobWithNoExistingTags()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0);
        $queue->pop()->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['a', 'b'], $data->tags);
    }

    public function testItCanAddTagsToAJobWithExistingTags()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 0, ['1', '2']);
        $queue->pop()->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['1', '2', 'a', 'b'], $data->tags);
    }

    public function testItCanRemoveExistingTags()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 0, ['1', '2', '3']);
        $queue->pop()->untag('2', '3');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['1'], $data->tags);
    }

    public function testRequeueJobWithNewTags()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 1, [], ['tag1','tag2']);
        $queue->pop()->requeue(null, ['tags' => ['nnn']]);

        $job = $queue->pop();
        $this->assertEquals(1, $job->priority);
        $this->assertEquals(['nnn'], $job->tags);
    }

    /**
     * @expectedException \Qless\Exceptions\InvalidJobException
     */
    public function testThrowsInvalidJobExceptionWhenRequeuingCancelledJob()
    {
        $queue = $this->client->queues['test-queue'];

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('SampleJobPerformClass', $data, 'jid-1', 0, 0, 1, [], ['tag1','tag2']);

        $job = $queue->pop();
        $this->client->cancel('jid-1');
        $job->requeue();
    }

    public function testJobCanCompleteSync()
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put(JobHandler::class, []);

        $this->client->config->set('sync-enabled', true);

        $jid = $queue->put(JobHandler::class, []);

        $job = $this->client->jobs[$jid];

        $this->client->config->clear('sync-enabled');

        $this->assertIsJob($job);
        $this->assertArrayHasKey('stack', $job->data->toArray());
        $this->assertFalse($job->getFailed());
    }

    public function testPopByIdOnce()
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put(JobHandler::class, []);

        $job = $queue->popByJid($jid);

        $this->assertIsJob($job);

        $job2 = $queue->popByJid($jid);

        $this->assertNull($job2);
    }

    public function testJobCantChangeFailToComplete()
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put(JobHandler::class, []);

        $job = $queue->popByJid($jid);

        $this->assertIsJob($job);

        $job->fail($job->getQueue(), 'Test Fail');

        // Fail to complete
        try {
            $job->complete();
            $exception = null;
        } catch (\Throwable $exception) {
        }

        $this->assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }

    public function testJobCantChangeCompleteToFail()
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put(JobHandler::class, []);

        $job = $queue->popByJid($jid);

        $this->assertIsJob($job);

        $job->complete();

        // Complete to fail
        try {
            $job->fail($job->getQueue(), 'Test Fail');
            $exception = null;
        } catch (\Throwable $exception) {
        }

        $this->assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }

    public function testJobCantCompleteTwice()
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put(JobHandler::class, []);

        $job = $queue->popByJid($jid);

        $this->assertIsJob($job);

        $job->complete();

        // Cant complete twice
        try {
            $job->complete();
            $exception = null;
        } catch (\Throwable $exception) {
        }

        $this->assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }

    public function testJobCantFailedTwice()
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put(JobHandler::class, []);

        $job = $queue->popByJid($jid);

        $this->assertIsJob($job);

        $job->fail($job->getQueue(), 'Test Fail');

        // Cant fail twice
        try {
            $job->fail($job->getQueue(), 'Test Fail');
            $exception = null;
        } catch (\Throwable $exception) {
        }

        $this->assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }
}

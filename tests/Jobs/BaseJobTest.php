<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\InvalidJobException;
use Qless\Exceptions\JobAlreadyFinishedException;
use Qless\Exceptions\LostLockException;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\UnknownPropertyException;
use Qless\Jobs\BaseJob;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;
use Throwable;

/**
 * Qless\Tests\Jobs\BaseJobTest
 *
 * @package Qless\Tests\Jobs
 */
class BaseJobTest extends QlessTestCase
{
    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionWhenSettingUnknownProperty(): void
    {
        $this->expectExceptionMessage("Setting unknown property: Qless\Jobs\BaseJob::foo");
        $this->expectException(UnknownPropertyException::class);
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleHandler', [], 'jid');

        $job = $this->client->jobs['jid'];

        /** @noinspection PhpUndefinedFieldInspection */
        $job->foo = 'bar';
    }

    /** @test */
    public function shouldChangeJobPriority(): void
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleHandler', [], 'jid');

        $job = $this->client->jobs['jid'];
        self::assertEquals(0, $job->priority);

        $job = $this->client->jobs['jid'];
        $job->priority = 18;

        $job = $this->client->jobs['jid'];

        self::assertEquals(18, $job->priority);
    }

    /** @test */
    public function shouldChangeFailedFlag(): void
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleHandler', [], 'jid');

        $job = $this->client->jobs['jid'];
        self::assertEquals(false, $job->failed);

        $job = $queue->pop();
        self::assertEquals(false, $job->failed);

        $job->fail('test', 'current job is failed');
        self::assertEquals(true, $job->failed);

        $job = $this->client->jobs['jid'];
        self::assertEquals(true, $job->failed);
    }

    /** @test */
    public function shouldRequeueJob(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 1, ['tag1','tag2']);
        $queue->pop()->requeue();

        $job = $queue->pop();

        self::assertEquals(1, $job->priority);
        self::assertEquals(['tag1','tag2'], $job->tags);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentClass(): void
    {
        $this->expectExceptionMessage("Could not find job class SampleJobPerformClass.");
        $this->expectException(InvalidArgumentException::class);
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
     *
     *
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentPerformMethod(): void
    {
        $this->expectExceptionMessage("Job class \"stdClass\" does not contain perform method \"myPerformMethod\".");
        $this->expectException(InvalidArgumentException::class);
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
     *
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithInvalidPerformMethod(): void
    {
        $this->expectException(InvalidArgumentException::class);
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
    public function shouldGetWorkerInstance(): void
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(JobHandler::class, ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']);

        $job = $queue->pop();
        $queue->pop();

        self::assertInstanceOf(JobHandler::class, $job->getInstance());
    }

    /** @test */
    public function shouldGetWorkerInstanceWithDefaultPerformMethod(): void
    {
        $queue = $this->client->queues['test-queue'];

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(JobHandler::class, []);

        $job = $queue->pop();
        $queue->pop();

        self::assertInstanceOf(JobHandler::class, $job->getInstance());
    }

    /**
     * @test
     */
    public function shouldThrowIOnCallingHeartbeatForInvalidJob(): void
    {
        $this->expectExceptionMessageRegExp('Job .* given out to another worker: worker-2 ');
        $this->expectException(LostLockException::class);
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
     *
     *
     */
    public function shouldThrowLostLockExceptionWhenHeartbeatFail(): void
    {
        $this->expectExceptionMessage("Job jid not currently running: waiting");
        $this->expectException(LostLockException::class);
        $queue = $this->client->queues['test-queue'];
        $queue->put('Foo', [], 'jid');

        $this->client->jobs['jid']->heartbeat();
    }

    /** @test */
    public function shouldProvideAccessToHeartbeat(): void
    {
        $this->client->config->set('heartbeat', 10);
        $this->client->queues['foo']->put('Foo', [], 'jid');

        $job = $this->client->queues['foo']->pop();
        $before = $job->ttl();

        $this->client->config->set('heartbeat', 20);
        $job->heartbeat();

        self::assertTrue($job->ttl() > $before);
    }

    /** @test */
    public function shouldStartTrackingJob(): void
    {
        $this->client->queues['foo']->put('Foo', [], 'jid');
        self::assertFalse($this->client->jobs['jid']->tracked);

        $this->client->jobs['jid']->track();
        self::assertTrue($this->client->jobs['jid']->tracked);

        $this->client->jobs['jid']->untrack();
        self::assertFalse($this->client->jobs['jid']->tracked);
    }

    /** @test */
    public function shouldRemoveOldTrackingJob(): void
    {
        $lifetime = 1;
        $lifetimeOld = $this->client->config->get('jobs-history');
        $this->client->config->set('jobs-history', $lifetime);

        $jid = $this->client->queues['foo']->put('Foo', []);

        $this->client->jobs[$jid]->track();

        self::assertTrue($this->client->jobs[$jid]->tracked);

        sleep($lifetime);

        $jid2 = $this->client->queues['foo']->put('Foo', []);

        $queue = $this->client->queues['foo'];

        $job = $queue->popByJid($jid2);

        $job->complete();

        self::assertNotContains($jid, $this->client->getJobs()->tracked());

        $this->client->config->set('jobs-history', $lifetimeOld);
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionWhenTimeoutFail(): void
    {
        $this->expectExceptionMessage("Job jid not running");
        $this->expectException(QlessException::class);
        $this->client->queues['foo']->put('Foo', [], 'jid');
        $this->client->jobs['jid']->timeout();
    }

    /** @test */
    public function shouldTimeoutJob(): void
    {
        $this->client->queues['foo']->put('Foo', [], 'jid');
        $job = $this->client->queues['foo']->pop();

        $job->timeout();
        unset($job);

        $job = $this->client->queues['foo']->pop();

        self::assertEquals('timed-out', $job->history[2]['what']);
    }

    /** @test */
    public function shouldGetCorrectTtl(): void
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleJobPerformClass', []);

        self::assertGreaterThan(55, $queue->pop()->ttl());
    }

    /** @test */
    public function shouldCompleteJob(): void
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('SampleJobPerformClass', []);

        self::assertEquals('complete', $queue->pop()->complete());
    }

    /** @test */
    public function shouldCompleteJobAndPutItInAnotherQueue(): void
    {
        $queue1 = $this->client->queues['test-queue-1'];
        $queue2 = $this->client->queues['test-queue-2'];

        $queue1->put('SampleJobPerformClass', ['size' => 2]);

        self::assertEquals(1, $queue1->length());
        self::assertEquals(0, $queue2->length());

        $job1 = $queue1->pop();
        self::assertEquals(2, $job1->data['size']);

        $job1->data['size'] -= 1;

        self::assertEquals('waiting', $job1->complete('test-queue-2'));

        self::assertEquals(0, $queue1->length());
        self::assertEquals(1, $queue2->length());

        $job2 = $queue2->pop();

        self::assertInstanceOf(BaseJob::class, $job2);
        self::assertEquals(1, $job2->data['size']);
    }

    /** @test */
    public function shouldNotPopFailedJob(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid');

        self::assertEquals('jid', $queue->pop()->fail('account', 'failed to connect'));
        self::assertNull($queue->pop('worker-1'));
    }

    public function testRetryDoesReturnJobAndDefaultsToFiveRetries(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid');

        $job1 = $queue->pop();
        $remaining = $job1->retry('account', 'failed to connect');
        self::assertEquals(4, $remaining);

        $job1 = $queue->pop();
        self::assertEquals('jid', $job1->jid);
    }

    public function testRetryDoesRespectRetryParameterWithOneRetry(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 1);

        $this->assertZero($queue->pop()->retry('account', 'failed to connect'));
        self::assertEquals('jid', $queue->pop()->jid);
    }

    public function testRetryDoesReturnNegativeWhenNoMoreAvailable(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 0);

        $job1 = $queue->pop();
        $remaining = $job1->retry('account', 'failed to connect');
        self::assertEquals(-1, $remaining);
    }

    public function testRetryTransitionsToFailedWhenExhaustedRetries(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 0);

        $job = $queue->pop();
        $job->retry('account', 'failed to connect');

        self::assertNull($queue->pop());
    }

    /** @test */
    public function shouldCancelRemovesJob(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0);
        $queue->put('SampleJobPerformClass', [], 'jid-2', 0, 0);

        self::assertEquals(['jid-1'], $queue->pop()->cancel());
    }

    /** @test */
    public function shouldCancelRemovesJobWithDependents(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', null, 0);
        $queue->put('SampleJobPerformClass', [], 'jid-2', null, 0, null, null, ['jid-1']);

        self::assertEquals(['jid-1', 'jid-2'], $queue->pop()->cancel(true));
    }

    /**
     * @test
     *
     *
     */
    public function shouldThrowExceptionOnCancelWithoutDependencies(): void
    {
        $this->expectExceptionMessage("jid-1 is a dependency of jid-2 but is not mentioned to be canceled");
        $this->expectException(QlessException::class);
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', null, 0);
        $queue->put('SampleJobPerformClass', [], 'jid-2', null, 0, null, null, ['jid-1']);

        $queue->pop()->cancel();
    }

    /** @test */
    public function shouldUnlockJobWhenDependenciesIsCompleted(): void
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put('MakeStuffing', ['lots' => 'of butter'], 'jid-1');
        $queue->put('MakeTurkey', ['with' => 'stuffing'], 'jid-2', null, null, null, null, [$jid]);

        $stuffingJob = $queue->pop();
        $turkeyJob = $queue->pop();

        self::assertEquals(['jid-2'], $stuffingJob->dependents);
        self::assertEquals('jid-1', $stuffingJob->jid);

        self::assertNull($turkeyJob);

        $stuffingJob->complete();
        $turkeyJob = $queue->pop();

        self::assertEquals('jid-2', $turkeyJob->jid);
    }

    /**
     * @test
     * @dataProvider jobPropertiesDataProvider
     *
     * @param string $property
     * @param string $type
     */
    public function shouldGetInternalProperties(string $property, string $type): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', []);
        $job = $queue->pop();

        self::assertEquals($type, gettype($job->{$property}));
    }

    public function jobPropertiesDataProvider(): array
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
     *
     *
     */
    public function shouldThrowExceptionWhenGetInaccessibleProperty(): void
    {
        $this->expectExceptionMessage("Getting unknown property: Qless\Jobs\BaseJob::foo");
        $this->expectException(UnknownPropertyException::class);
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', []);
        $job = $queue->pop();

        /** @noinspection PhpUndefinedFieldInspection */
        $job->foo;
    }

    /** @test */
    public function shouldTreatedLikeAString(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'job-id');
        $job = $queue->pop();

        $expected = 'Qless\Jobs\BaseJob SampleJobPerformClass job-id / test-queue';

        self::assertEquals($expected, (string) $job);
        self::assertEquals($expected, $job->__toString());
    }

    /** @test */
    public function shouldProvideAccessToTheDataUsingArrayNotation(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', ['foo' => 'bar'], 'job-id');
        $job = $queue->pop();

        self::assertTrue(isset($job['foo']));
        self::assertEquals('bar', $job['foo']);

        $job['baz'] = 'buz';

        self::assertTrue(isset($job['baz']));
        self::assertEquals('buz', $job['baz']);

        unset($job['baz']);

        self::assertFalse(isset($job['baz']));
    }

    public function testItCanAddTagsToAJobWithNoExistingTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0);
        $queue->pop()->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        self::assertEquals(['a', 'b'], $data->tags);
    }

    public function testItCanAddTagsToAJobWithExistingTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 0, ['1', '2']);
        $queue->pop()->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        self::assertEquals(['1', '2', 'a', 'b'], $data->tags);
    }

    public function testItCanRemoveExistingTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 0, ['1', '2', '3']);
        $queue->pop()->untag('2', '3');

        $data = json_decode($this->client->get('jid-1'));
        self::assertEquals(['1'], $data->tags);
    }

    public function testRequeueJobWithNewTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, 1, [], ['tag1','tag2']);
        $queue->pop()->requeue(null, ['tags' => ['nnn']]);

        $job = $queue->pop();
        self::assertEquals(1, $job->priority);
        self::assertEquals(['nnn'], $job->tags);
    }

    public function testThrowsInvalidJobExceptionWhenRequeuingCancelledJob(): void
    {
        $this->expectException(InvalidJobException::class);
        $queue = $this->client->queues['test-queue'];

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('SampleJobPerformClass', $data, 'jid-1', 0, 0, 1, [], ['tag1','tag2']);

        $job = $queue->pop();
        $this->client->cancel('jid-1');
        $job->requeue();
    }

    public function testJobCanCompleteSync(): void
    {
        $this->client->config->set('sync-enabled', true);
        $queue = $this->client->queues['test-queue-sync-enabled'];
        $jid = $queue->put(JobHandler::class, []);
        $job = $this->client->jobs[$jid];

        $this->assertIsJob($job);
        self::assertArrayHasKey('stack', $job->data->toArray());
        self::assertFalse($job->getFailed());

        $this->client->config->clear('sync-enabled');
        $queue = $this->client->queues['test-queue-sync-disabled'];
        $jid = $queue->put(JobHandler::class, []);
        $job = $this->client->jobs[$jid];

        $this->assertIsJob($job);
        self::assertArrayNotHasKey('stack', $job->data->toArray());
    }

    public function testPopByIdOnce(): void
    {
        $queue = $this->client->queues['test-queue'];

        $jid = $queue->put(JobHandler::class, []);

        $job = $queue->popByJid($jid);

        $this->assertIsJob($job);

        $job2 = $queue->popByJid($jid);

        self::assertNull($job2);
    }

    public function testJobCantChangeFailToComplete(): void
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
        } catch (Throwable $exception) {
        }

        self::assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }

    public function testJobCantChangeCompleteToFail(): void
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
        } catch (Throwable $exception) {
        }

        self::assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }

    public function testJobCantCompleteTwice(): void
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
        } catch (Throwable $exception) {
        }

        self::assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }

    public function testJobCantFailedTwice(): void
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
        } catch (Throwable $exception) {
        }

        self::assertInstanceOf(JobAlreadyFinishedException::class, $exception);
    }
}

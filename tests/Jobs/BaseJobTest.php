<?php

namespace Qless\Tests\Jobs;

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
}

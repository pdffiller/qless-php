<?php

namespace Qless\Tests\Jobs;

use Qless\Jobs\Job;
use Qless\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;

/**
 * Qless\Tests\Jobs\JobTest
 *
 * @package Qless\Tests\Jobs
 */
class JobTest extends QlessTestCase
{
    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     * @expectedExceptionMessage Could not find job class SampleJobPerformClass.
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentClass()
    {
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put('SampleJobPerformClass', ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']);

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
        $queue = new Queue('testQueue', $this->client);

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

        $queue = new Queue('testQueue', $this->client);

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
        $queue = new Queue('testQueue', $this->client);

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
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(JobHandler::class, ['payload' => 'otherData']);

        $job = $queue->pop();
        $queue->pop();

        $this->assertInstanceOf(JobHandler::class, $job->getInstance());
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\QlessException
     * @expectedExceptionMessageRegExp "Job .* given out to another worker: worker-2 "
     */
    public function shouldThrowIOnCallingHeartbeatForInvalidJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put('SampleJobPerformClass', ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']);

        $job = $queue->pop('worker-1');
        $queue->pop('worker-2');

        $job->heartbeat();
    }

    /** @test */
    public function shouldGetCorrectTtl()
    {
        $queue = new Queue('testQueue', $this->client);
        $queue->put('SampleJobPerformClass', []);

        $this->assertGreaterThan(55, $queue->pop()->ttl());
    }

    /** @test */
    public function shouldCompleteJob()
    {
        $queue = new Queue('testQueue', $this->client);
        $queue->put('SampleJobPerformClass', []);

        $this->assertEquals('complete', $queue->pop()->complete());
    }

    /** @test */
    public function shouldCompleteJobAndPutItInAnotherQueue()
    {
        $queue1 = new Queue('test-queue-1', $this->client);
        $queue2 = new Queue('test-queue-2', $this->client);

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

        $this->assertInstanceOf(Job::class, $job2);
        $this->assertEquals(1, $job2->data['size']);
    }

    /** @test */
    public function shouldNotPopFailedJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid');

        $this->assertEquals('jid', $queue->pop()->fail('account', 'failed to connect'));
        $this->assertNull($queue->pop('worker-1'));
    }

    public function testRetryDoesReturnJobAndDefaultsToFiveRetries()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('SampleJobPerformClass', $testData, 'jid');

        $job1 = $queue->pop();
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(4, $remaining);

        $job1 = $queue->pop();
        $this->assertEquals('jid', $job1->jid);
    }

    public function testRetryDoesRespectRetryParameterWithOneRetry()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid', 0, 1);

        $this->assertZero($queue->pop()->retry('account', 'failed to connect'));
        $this->assertEquals('jid', $queue->pop()->jid);
    }

    public function testRetryDoesReturnNegativeWhenNoMoreAvailable()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('SampleJobPerformClass', $testData, 'jid', 0, 0);

        $job1 = $queue->pop();
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(-1, $remaining);
    }

    public function testRetryTransitionsToFailedWhenExhaustedRetries()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('SampleJobPerformClass', $testData, 'jid', 0, 0);

        $job = $queue->pop();
        $job->retry('account', 'failed to connect');

        $this->assertNull($queue->pop());
    }

    /** @test */
    public function shouldCancelRemovesJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];

        $queue->put('SampleJobPerformClass', $data, 'jid-1', 0, 0);
        $queue->put('SampleJobPerformClass', $data, 'jid-2', 0, 0);

        $this->assertEquals(['jid-1'], $queue->pop()->cancel());
    }

    /** @test */
    public function shouldCancelRemovesJobWithDependents()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];

        $queue->put('SampleJobPerformClass', $data, 'jid-1', 0, 0);
        $queue->put('SampleJobPerformClass', $data, 'jid-2', 0, 0, true, 0, [], 0, [], ['jid-1']);

        $this->assertEquals(['jid-1', 'jid-2'], $queue->pop()->cancel(true));
    }

    /**
     * @expectedException \Qless\Exceptions\QlessException
     */
    public function testCancelThrowsExceptionWithDependents()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];

        $queue->put('SampleJobPerformClass', $data, 'jid-1', 0, 0);
        $queue->put('SampleJobPerformClass', $data, 'jid-2', 0, 0, true, 0, [], 0, [], ['jid-1']);

        $queue->pop()->cancel();
    }

    public function testItCanAddTagsToAJobWithNoExistingTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0);
        $queue->pop()->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['a', 'b'], $data->tags);
    }

    public function testItCanAddTagsToAJobWithExistingTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, true, 0, [], 0, ['1', '2']);
        $queue->pop()->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['1', '2', 'a', 'b'], $data->tags);
    }

    public function testItCanRemoveExistingTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, true, 0, [], 0, ['1', '2', '3']);
        $queue->pop()->untag('2', '3');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['1'], $data->tags);
    }

    /** @test */
    public function shouldRequeueJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, true, 1, [], 5, ['tag1','tag2']);
        $queue->pop()->requeue();

        $job = $queue->pop();

        $this->assertEquals(1, $job->priority);
        $this->assertEquals(['tag1','tag2'], $job->tags);
    }

    public function testRequeueJobWithNewTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $queue->put('SampleJobPerformClass', [], 'jid-1', 0, 0, true, 1, [], 5, ['tag1','tag2']);
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
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('SampleJobPerformClass', $data, 'jid-1', 0, 0, true, 1, [], 5, ['tag1','tag2']);

        $job = $queue->pop();
        $this->client->cancel('jid-1');
        $job->requeue();
    }
}

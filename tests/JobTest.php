<?php

namespace Qless\Tests;

use Qless\Queue;
use Qless\Tests\Stubs\WorkerStub2;

/**
 * Qless\Tests\JobTest
 *
 * @package Qless\Tests
 */
class JobTest extends QlessTestCase
{
    /**
     * @test
     * @expectedException \Qless\Exceptions\RuntimeException
     * @expectedExceptionMessage Could not find job class Sample\TestWorkerImpl.
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentClass()
    {
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(
            'Sample\TestWorkerImpl',
            ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']
        );

        $job1 = $queue->pop('worker-1')[0];
        $queue->pop('worker-2');

        $job1->getInstance();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\RuntimeException
     * @expectedExceptionMessage Job class "stdClass" does not contain perform method "myPerformMethod".
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithNonExistentPerformMethod()
    {
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(
            \stdClass::class,
            ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']
        );

        $job1 = $queue->pop('worker-1')[0];
        $queue->pop('worker-2');

        $job1->getInstance();
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\RuntimeException
     */
    public function shouldThrowsExpectedExceptionWhenGetInstanceWithInvalidPerformMethod()
    {
        $this->expectExceptionMessage(
            'Job class "Qless\Tests\Stubs\WorkerStub2" does not contain perform method "myPerformMethod2".'
        );

        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(
            WorkerStub2::class,
            ['performMethod' => 'myPerformMethod2', 'payload' => 'otherData']
        );

        $job1 = $queue->pop('worker-1')[0];
        $queue->pop('worker-2');

        $job1->getInstance();
    }

    /** @test */
    public function shouldGetWorkerInstance()
    {
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(
            WorkerStub2::class,
            ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']
        );

        $job1 = $queue->pop('worker-1')[0];
        $queue->pop('worker-2');

        $this->assertInstanceOf(WorkerStub2::class, $job1->getInstance());
    }

    /** @test */
    public function shouldGetWorkerInstanceWithDefaultPerformMethod()
    {
        $queue = new Queue('testQueue', $this->client);

        $this->client->config->set('heartbeat', -10);
        $this->client->config->set('grace-period', 0);

        $queue->put(WorkerStub2::class, ['payload' => 'otherData']);

        $job1 = $queue->pop('worker-1')[0];
        $queue->pop('worker-2');

        $this->assertInstanceOf(WorkerStub2::class, $job1->getInstance());
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

        $queue->put(
            'Sample\TestWorkerImpl',
            ['performMethod' => 'myPerformMethod', 'payload' => 'otherData']
        );

        $job1 = $queue->pop('worker-1')[0];
        $queue->pop('worker-2');
        $job1->heartbeat();
    }

    public function testCanGetCorrectTTL()
    {
        $queue = new Queue('testQueue', $this->client);
        $queue->put("Sample\\TestWorkerImpl", []);
        $job = $queue->pop('worker-1')[0];
        $ttl = $job->ttl();
        $this->assertGreaterThan(55, $ttl);
    }

    public function testCompleteJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", $testData);

        $job1 = $queue->pop('worker-1')[0];
        $res = $job1->complete();
        $this->assertEquals('complete', $res);
    }

    public function testFailJobCannotBePopped()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put("Sample\\TestWorkerImpl", $testData, 'jid');

        $job1 = $queue->pop('worker-1')[0];
        $res = $job1->fail('account', 'failed to connect');
        $this->assertEquals('jid', $res);

        $job1 = $queue->pop('worker-1');
        $this->assertEmpty($job1);
    }

    public function testRetryDoesReturnJobAndDefaultsToFiveRetries()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('Sample\TestWorkerImpl', $testData, 'jid');

        $job1 = $queue->pop('worker-1')[0];
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(4, $remaining);

        $job1 = $queue->pop('worker-1')[0];
        $this->assertEquals('jid', $job1->getId());
    }

    public function testRetryDoesRespectRetryParameterWithOneRetry()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('Sample\TestWorkerImpl', $testData, 'jid', 0, 1);

        $job1 = $queue->pop('worker-1')[0];
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(0, $remaining);

        $job1 = $queue->pop('worker-1')[0];
        $this->assertEquals('jid', $job1->getId());
    }

    public function testRetryDoesReturnNegativeWhenNoMoreAvailable()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('Sample\TestWorkerImpl', $testData, 'jid', 0, 0);

        $job1 = $queue->pop('worker-1')[0];
        $remaining = $job1->retry('account', 'failed to connect');
        $this->assertEquals(-1, $remaining);
    }

    public function testRetryTransitionsToFailedWhenExhaustedRetries()
    {
        $queue = new Queue('testQueue', $this->client);

        $testData = ["performMethod" => 'myPerformMethod', "payload" => "otherData"];
        $queue->put('Sample\TestWorkerImpl', $testData, 'jid', 0, 0);

        $job1 = $queue->pop('worker-1')[0];
        $job1->retry('account', 'failed to connect');

        $job1 = $queue->pop('worker-1');
        $this->assertEmpty($job1);
    }

    public function testCancelRemovesJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0);
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-2', 0, 0);

        $job1 = $queue->pop('worker-1')[0];
        $res = $job1->cancel();

        $this->assertEquals(['jid-1'], $res);
    }

    public function testCancelRemovesJobWithDependents()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0);
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-2', 0, 0, true, 0, [], 0, [], ['jid-1']);

        $job1 = $queue->pop('worker-1')[0];
        $res = $job1->cancel(true);

        $this->assertEquals(['jid-1', 'jid-2'], $res);
    }

    /**
     * @expectedException \Qless\Exceptions\QlessException
     */
    public function testCancelThrowsExceptionWithDependents()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0);
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-2', 0, 0, true, 0, [], 0, [], ['jid-1']);

        $job1 = $queue->pop('worker-1')[0];
        $job1->cancel();
    }

    public function testItCanAddTagsToAJobWithNoExistingTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0);

        $job1 = $queue->pop('worker-1')[0];
        $job1->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['a', 'b'], $data->tags);
    }

    public function testItCanAddTagsToAJobWithExistingTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0, true, 0, [], 0, ['1', '2']);

        $job1 = $queue->pop('worker-1')[0];
        $job1->tag('a', 'b');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['1', '2', 'a', 'b'], $data->tags);
    }

    public function testItCanRemoveExistingTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0, true, 0, [], 0, ['1', '2', '3']);

        $job1 = $queue->pop('worker-1')[0];
        $job1->untag('2', '3');

        $data = json_decode($this->client->get('jid-1'));
        $this->assertEquals(['1'], $data->tags);
    }

    public function testRequeueJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0, true, 1, [], 5, ['tag1','tag2']);

        $job = $queue->pop('worker-1')[0];
        $job->requeue();

        $job = $queue->pop('worker-1')[0];
        $this->assertEquals(1, $job->getPriority());
        $this->assertEquals(['tag1','tag2'], $job->getTags());
    }

    public function testRequeueJobWithNewTags()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0, true, 1, [], 5, ['tag1','tag2']);

        $job = $queue->pop('worker-1')[0];
        $job->requeue(['tags' => ['nnn']]);

        $job = $queue->pop('worker-1')[0];
        $this->assertEquals(1, $job->getPriority());
        $this->assertEquals(['nnn'], $job->getTags());
    }

    /**
     * @expectedException \Qless\Exceptions\InvalidJobException
     */
    public function testThrowsInvalidJobExceptionWhenRequeuingCancelledJob()
    {
        $queue = new Queue('testQueue', $this->client);

        $data = ['performMethod' => 'myPerformMethod', 'payload' => 'otherData'];
        $queue->put('Sample\TestWorkerImpl', $data, 'jid-1', 0, 0, true, 1, [], 5, ['tag1','tag2']);

        $job = $queue->pop('worker-1')[0];
        $this->client->cancel('jid-1');
        $job->requeue();
    }
}

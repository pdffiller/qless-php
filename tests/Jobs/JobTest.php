<?php

namespace Qless\Tests\Jobs;

use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Jobs\JobTest
 *
 * @todo Refactor
 *
 * @package Qless\Tests\Jobs
 */
class JobTest extends QlessTestCase
{
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
}

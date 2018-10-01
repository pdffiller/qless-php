<?php

namespace Qless\Tests\Jobs;

use Qless\Jobs\JobData;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Jobs\RecurringJobTest
 *
 * @package Qless\Tests\Jobs
 */
class RecurringJobTest extends QlessTestCase
{
    /**
     * @test
     * @dataProvider jobPropertiesDataProvider
     *
     * @param string $property
     * @param string $type
     */
    public function shouldGetInternalProperties(string $property, string $type)
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid', 60);
        $job = $this->client->jobs['jid'];

        $this->assertEquals($type, gettype($job->{$property}));
    }

    public function jobPropertiesDataProvider()
    {
        return [
            ['jid', 'string'],
            ['klass', 'string'],
            ['queue', 'string'],
            ['tags', 'array'],
            ['priority', 'integer'],
            ['retries', 'integer'],
            ['data', 'object'],
            ['interval', 'integer'],
            ['count', 'integer'],
            ['backlog', 'integer'],
        ];
    }

    /** @test */
    public function shouldChangeJobPriority()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid');
        $this->assertEquals(0, $this->client->jobs['jid']->priority);

        $this->client->jobs['jid']->priority = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->priority);
    }

    /** @test */
    public function shouldChangeJobInterval()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid');
        $this->assertEquals(60, $this->client->jobs['jid']->interval);

        $this->client->jobs['jid']->interval = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->interval);
    }

    /** @test */
    public function shouldChangeJobRetries()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid', null, null, 2);
        $this->assertEquals(2, $this->client->jobs['jid']->retries);

        $this->client->jobs['jid']->retries = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->retries);
    }

    /** @test */
    public function shouldChangeJobData()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid');
        $this->assertEquals([], $this->client->jobs['jid']->data->toArray());

        $this->client->jobs['jid']->data = ['foo' => 'bar'];
        $this->assertEquals(['foo' => 'bar'], $this->client->jobs['jid']->data->toArray());

        $this->client->jobs['jid']->data = new JobData(['some' => 'payload']);
        $this->assertEquals(['some' => 'payload'], $this->client->jobs['jid']->data->toArray());

        $this->client->jobs['jid']->data = '{"foo": "bar"}';
        $this->assertEquals(['foo' => 'bar'], $this->client->jobs['jid']->data->toArray());
    }

    /**
     * @test
     * @expectedException \Qless\Exceptions\InvalidArgumentException
     */
    public function shouldThrowExceptionWhenSetInvalidData()
    {
        $this->expectExceptionMessage(
            "Job's data must be either an array, or a JobData instance, or a JSON string, integer given."
        );

        $this->client->queues['test-queue']->recur('Foo', [], 'jid');
        $this->client->jobs['jid']->data = 10;
    }

    /** @test */
    public function shouldChangeJobKlass()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid');
        $this->assertEquals('Foo', $this->client->jobs['jid']->klass);

        $this->client->jobs['jid']->klass = 'Bar';
        $this->assertEquals('Bar', $this->client->jobs['jid']->klass);
    }

    /** @test */
    public function shouldChangeJobBacklog()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid');
        $this->assertEquals(0, $this->client->jobs['jid']->backlog);

        $this->client->jobs['jid']->backlog = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->backlog);
    }
}

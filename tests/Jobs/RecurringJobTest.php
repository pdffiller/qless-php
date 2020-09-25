<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\InvalidArgumentException;
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
    public function shouldGetInternalProperties(string $property, string $type): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        $job = $this->client->jobs['jid'];

        self::assertEquals($type, gettype($job->{$property}));
    }

    public function jobPropertiesDataProvider(): array
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
    public function shouldChangeJobPriority(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        self::assertEquals(0, $this->client->jobs['jid']->priority);

        $this->client->jobs['jid']->priority = 10;
        self::assertEquals(10, $this->client->jobs['jid']->priority);
    }

    /** @test */
    public function shouldChangeJobInterval(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        self::assertEquals(60, $this->client->jobs['jid']->interval);

        $this->client->jobs['jid']->interval = 10;
        self::assertEquals(10, $this->client->jobs['jid']->interval);
    }

    /** @test */
    public function shouldChangeJobRetries(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid', 2);
        self::assertEquals(2, $this->client->jobs['jid']->retries);

        $this->client->jobs['jid']->retries = 10;
        self::assertEquals(10, $this->client->jobs['jid']->retries);
    }

    /** @test */
    public function shouldChangeJobData(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        self::assertEquals([], $this->client->jobs['jid']->data->toArray());

        $this->client->jobs['jid']->data = ['foo' => 'bar'];
        self::assertEquals(['foo' => 'bar'], $this->client->jobs['jid']->data->toArray());

        $this->client->jobs['jid']->data = new JobData(['some' => 'payload']);
        self::assertEquals(['some' => 'payload'], $this->client->jobs['jid']->data->toArray());

        $this->client->jobs['jid']->data = '{"foo": "bar"}';
        self::assertEquals(['foo' => 'bar'], $this->client->jobs['jid']->data->toArray());
    }

    /**
     * @test
     *
     */
    public function shouldThrowExceptionWhenSetInvalidData(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Job's data must be either an array, or a JobData instance, or a JSON string, integer given."
        );

        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        $this->client->jobs['jid']->data = 10;
    }

    /** @test */
    public function shouldChangeJobKlass(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        self::assertEquals('Foo', $this->client->jobs['jid']->klass);

        $this->client->jobs['jid']->klass = 'Bar';
        self::assertEquals('Bar', $this->client->jobs['jid']->klass);
    }

    /** @test */
    public function shouldChangeJobBacklog(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        self::assertEquals(0, $this->client->jobs['jid']->backlog);

        $this->client->jobs['jid']->backlog = 10;
        self::assertEquals(10, $this->client->jobs['jid']->backlog);
    }

    /** @test */
    public function shouldRequeueJob(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
        self::assertEquals('test-queue', $this->client->jobs['jid']->queue);

        $this->client->jobs['jid']->requeue('bar');
        self::assertEquals('bar', $this->client->jobs['jid']->queue);
    }

    /** @test */
    public function shouldCancelJob(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');

        self::assertEquals(1, $this->client->jobs['jid']->cancel());
        self::assertNull($this->client->jobs['jid']);
    }

    /** @test */
    public function shouldSetTags(): void
    {
        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');

        self::assertEquals([], $this->client->jobs['jid']->tags);

        $this->client->jobs['jid']->tag('foo', 'bar');
        self::assertEquals(['foo', 'bar'], $this->client->jobs['jid']->tags);

        self::assertEquals(['foo', 'bar'], $this->client->jobs['jid']->tags);

        $this->client->jobs['jid']->untag('bar');
        self::assertEquals(['foo'], $this->client->jobs['jid']->tags);

        $this->client->jobs['jid']->untag('baz');
        self::assertEquals(['foo'], $this->client->jobs['jid']->tags);
    }
}

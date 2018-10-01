<?php

namespace Qless\Tests\Jobs;

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
        $this->client->queues['test-queue']->recur('Foo', [], 'jid', 60);
        $this->assertEquals(0, $this->client->jobs['jid']->priority);

        $this->client->jobs['jid']->priority = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->priority);
    }

    /** @test */
    public function shouldChangeJobRetries()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid', 60, null, 2);
        $this->assertEquals(2, $this->client->jobs['jid']->retries);

        $this->client->jobs['jid']->retries = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->retries);
    }
}

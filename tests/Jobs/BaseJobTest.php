<?php

namespace Qless\Tests\Jobs;

use Qless\Tests\QlessTestCase;

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
     * @expectedExceptionMessage Setting unknown property: Qless\Jobs\Job::foo
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
}

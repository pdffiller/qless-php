<?php

namespace Qless\Tests\Jobs;

use Qless\Jobs\RecurringJob;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;

/**
 * Qless\Tests\Jobs\RecurringJobTest
 *
 * @package Qless\Tests\Jobs
 */
class RecurringJobTest extends QlessTestCase
{
    /** @test */
    public function shouldChangeJobPriority()
    {
        $this->client->queues['test-queue']->recur('Foo', [], 'jid', 60);
        $this->assertEquals(0, $this->client->jobs['jid']->priority);

        $this->client->jobs['jid']->priority = 10;
        $this->assertEquals(10, $this->client->jobs['jid']->priority);
    }
}

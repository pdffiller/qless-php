<?php

namespace Qless\Tests\Events;

use Qless\Jobs\Job;
use Qless\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;
use Qless\Tests\Stubs\JobSubscriber;

/**
 * Qless\Tests\Events\JobEventsTest
 *
 * @package Qless\Tests\Events
 */
class JobEventsTest extends QlessTestCase
{
    private $status;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->status = new \stdClass();
    }

    /** @test */
    public function shouldSubscribeToAroundPerformEvents()
    {
        $this->putJob();
        $this->subscribeToEvent();

        $job = $this->popJob();

        $this->assertFalse(isset($this->status->triggered));
        $this->assertEquals([], $job->data->toArray());

        $job->perform();

        $this->assertTrue(isset($this->status->triggered));

        $expected = [
            [
                'event' => 'beforePerform',
                'jid'   => $job->jid,
            ],
            [
                'event' => 'afterPerform',
                'jid'   => $job->jid,
            ]
        ];

        $this->assertEquals($expected, $this->status->triggered);

        $expected = [
            'stack' => [
                JobSubscriber::class . '::beforePerform',
                JobHandler::class . '::perform',
                JobSubscriber::class . '::afterPerform',
            ],
        ];

        $this->assertEquals($expected, $job->data->toArray());
    }

    private function popJob(): Job
    {
        $queue = new Queue('testing', $this->client);
        return $queue->pop();
    }

    private function subscribeToEvent()
    {
        $this->client->getEventsManager()->attach('job', new JobSubscriber($this->status));
    }

    private function putJob(): void
    {
        $queue = new Queue('testing', $this->client);
        $queue->put(JobHandler::class, []);
    }
}

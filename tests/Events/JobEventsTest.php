<?php

namespace Qless\Tests\Events;

use Qless\Jobs\BaseJob;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;
use Qless\Tests\Stubs\JobSubscriber;
use Qless\Events\User\Queue\BeforeEnqueue;
use stdClass;

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

        $this->status = new stdClass();
    }

    /**
     * @test
     */
    public function shouldSubscribeToAroundPerformEvents(): void
    {
        $this->putJob();
        $this->subscribeToJobEvents();

        $job = $this->popJob();

        self::assertFalse(isset($this->status->triggered));
        self::assertEquals([], $job->data->toArray());

        $job->perform();

        self::assertTrue(isset($this->status->triggered));

        $expected = [
            [
                'event' => 'job:beforePerform',
                'jid'   => $job->jid,
            ],
            [
                'event' => 'job:afterPerform',
                'jid'   => $job->jid,
            ]
        ];

        self::assertEquals($expected, $this->status->triggered);

        $expected = [
            'stack' => [
                JobSubscriber::class . '::beforePerform',
                JobHandler::class . '::perform',
                JobSubscriber::class . '::afterPerform',
            ],
        ];

        self::assertEquals($expected, $job->data->toArray());
    }

    /**
     * @test
     */
    public function shouldAppendJobDataViaEventSubscriber(): void
    {
        $this->subscribeToQueueEvents();
        $this->putJob(['payload' => 'data']);

        $job = $this->popJob();

        self::assertEquals(
            [
                'payload' => 'data',
                'metadata' => [
                    'user_id' => 123,
                    'ip_address' => '127.0.0.1',
                ],
            ],
            $job->data->toArray()
        );
    }

    private function popJob(): BaseJob
    {
        $queue = new Queue('testing', $this->client);
        return $queue->pop();
    }

    private function subscribeToQueueEvents(): void
    {
        $this->client
            ->getEventsManager()
            ->attach('queue:beforeEnqueue', function (BeforeEnqueue $event) {
                $event->getData()['metadata'] = [
                    'user_id' => 123,
                    'ip_address' => '127.0.0.1',
                ];
            });
    }

    private function subscribeToJobEvents(): void
    {
        $this->client->getEventsManager()->attach('job', new JobSubscriber($this->status));
    }

    private function putJob(array $data = []): void
    {
        $queue = new Queue('testing', $this->client);
        $queue->put(JobHandler::class, $data);
    }
}

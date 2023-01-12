<?php

namespace Qless\Tests\Queues;

use Qless\Queues\Queue;
use Qless\Tests\Support\LightClientTrait;

class QueueLightTest extends QueueTest
{
    use LightClientTrait;

    /**
     * @test
     */
    public function shouldGenerateAJobIdIfNotProvided(): void
    {
        $queue = new Queue('test-queue', $this->client);

        self::assertRegExp('/^[[:xdigit:]]{32}$/', $queue->put('Xxx\Yyy', []));
    }

    /**
     * @test
     */
    public function shouldPutJobWithPriority(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }

    /**
     * @test
     */
    public function shouldPopJobsWithHigherPriorityFirst(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }

    public function testHighPriorityJobPoppedBeforeLowerPriorityJobs(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }
}

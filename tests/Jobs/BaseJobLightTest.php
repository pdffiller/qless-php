<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\UnsupportedMethodException;
use Qless\Tests\Support\LightClientTrait;

class BaseJobLightTest extends BaseJobTest
{
    use LightClientTrait;

    public function testItCanAddTagsToAJobWithNoExistingTags(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testItCanAddTagsToAJobWithNoExistingTags();
    }

    public function testItCanAddTagsToAJobWithExistingTags(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testItCanAddTagsToAJobWithExistingTags();
    }

    public function testItCanRemoveExistingTags(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testItCanRemoveExistingTags();
    }

    public function testRequeueJobWithNewTags(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }

    /**
     * @test
     */
    public function shouldChangeJobPriority(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }

    /**
     * @test
     */
    public function shouldRequeueJob(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('SampleJobPerformClass', [], 'jid-1');
        $queue->pop()->requeue();

        $job = $queue->pop();

        self::assertEquals('jid-1', $job->jid);
    }

    /**
     * @test
     */
    public function shouldCancelRemovesJobWithDependents(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }

    /**
     * @test
     */
    public function shouldThrowExceptionOnCancelWithoutDependencies(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }

    /**
     * @test
     */
    public function shouldUnlockJobWhenDependenciesIsCompleted(): void
    {
        $this->markTestSkipped('Unsupported feature');
    }
}

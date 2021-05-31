<?php
namespace Qless\Tests\Workers;

use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;
use Qless\Workers\ResourceLimitedWorkerInterface;

abstract class WorkerLimitTest extends QlessTestCase
{
    protected const QUEUE_SIZE = 100;

    public function testNumberJobs(): void
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setMaximumNumberJobs(1);
        $worker->run();

        self::assertEquals(99, $queue->length());
    }

    /**
     * Very weird test. On powerful computer worker can consume all queue for that second
     */
    public function testTimeLimitWorker(): void
    {
        $queue = $this->getQueue(true);
        $worker = $this->getWorker();
        $worker->setTimeLimit(1);
        $worker->run();

        self::assertNotEmpty($queue->length(), 'Queue length is '.$queue->length());
    }

    public function testMemoryLimitWorker(): void
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setMemoryLimit(1);
        $worker->run();

        self::assertNotEmpty($queue->length());
    }


    private function getQueue(bool $sleep = false): Queue
    {
        $queue = new Queue('test-queue', $this->client);
        for ($i = 0; $i < self::QUEUE_SIZE; $i++) {
            $queue->put(JobHandler::class, \compact('sleep'));
        }

        return $queue;
    }

    abstract protected function getWorker(): ResourceLimitedWorkerInterface;
}

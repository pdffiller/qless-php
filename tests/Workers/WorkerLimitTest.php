<?php


namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;
use Qless\Workers\ForkingWorker;

class WorkerLimitTest extends QlessTestCase
{
    public function testNumberJobs(): void
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setMaximumNumberJobs(1);
        $worker->run();

        self::assertEquals(99, $queue->length());
    }

    public function testTimeLimitWorker(): void
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setTimeLimit(1);
        $worker->run();

        self::assertNotEmpty($queue->length());
    }

    public function testMemoryLimitWorker(): void
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setMemoryLimit(1);
        $worker->run();

        self::assertNotEmpty($queue->length());
    }


    private function getQueue(): Queue
    {
        $queue = new Queue('test-queue', $this->client);
        for ($i = 0; $i < 100; $i++) {
            $queue->put(JobHandler::class, []);
        }

        return $queue;
    }

    private function getWorker(): ForkingWorker
    {
        return new ForkingWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

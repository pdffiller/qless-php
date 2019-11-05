<?php


namespace Qless\Tests\Workers;

use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\JobHandler;
use Qless\Workers\ForkingWorker;

class WorkerLimitTest extends QlessTestCase
{
    public function testNumberJobs()
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setMaximumNumberJobs(1);
        $worker->run();

        $this->assertEquals(99, $queue->length());
    }

    public function testTimeLimitWorker()
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setTimeLimit(1);
        $worker->run();

        $this->assertNotEmpty($queue->length());
    }

    public function testMemoryLimitWorker()
    {
        $queue = $this->getQueue();
        $worker = $this->getWorker();
        $worker->setMemoryLimit(1);
        $worker->run();

        $this->assertNotEmpty($queue->length());
    }


    private function getQueue()
    {
        $queue = new Queue('test-queue', $this->client);
        for ($i = 0; $i < 100; $i++) {
            $queue->put(JobHandler::class, []);
        }

        return $queue;
    }

    private function getWorker()
    {
        return new ForkingWorker(
            new OrderedReserver($this->client->queues, ['test-queue']),
            $this->client
        );
    }
}

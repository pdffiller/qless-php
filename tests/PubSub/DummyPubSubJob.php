<?php

namespace Qless\Tests\PubSub;

use Qless\Jobs\BaseJob;
use Qless\PubSub\Manager;
use function fwrite;
use function sleep;

class DummyPubSubJob
{
    public function perform(BaseJob $job): void
    {


        $type = $job->data['type'] ?? null;

        switch ($type) {
            case Manager::EVENT_CANCELED:
            case Manager::EVENT_POPPED:
            case Manager::EVENT_TRACK:
                $job->cancel();
                break;
            case Manager::EVENT_COMPLETED:
                $job->complete();
                break;
            case Manager::EVENT_FAILED:
                $job->fail('test', 'triggering deliberate fail');
                break;
            case Manager::EVENT_STALLED:
                if ($job->retries === $job->remaining) {
                    sleep($job->ttl() + 1);
                }
                break;
            case Manager::EVENT_UNTRACK:
                $job->untrack();
                break;

            case Manager::EVENT_PUT:
                $job->requeue(ManagerTest::QUEUE_NAME . '2');
                break;

            default:
                fwrite(STDERR, 'Invalid job type provided: ' . var_export($type, true) . PHP_EOL);
        }
    }
}

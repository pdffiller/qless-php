<?php

namespace Qless\Tests\Queues;

use Qless\Exceptions\UnsupportedMethodException;
use Qless\Queues\Queue;
use Qless\Tests\Support\LightClientTrait;

class JobCollectionLightTest extends JobCollectionTest
{
    use LightClientTrait;

    protected function populateQueue(Queue $queue): void
    {
        $queue->put('XX\\YY\\ZZ', ['foo' => 'bar'], 'job-1');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'bar2'], 'job-2');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'boo'], 'job-3');
        $queue->put('XX\\YY\\ZZ', ['foo' => 'baz'], 'job-4', 300);
        $queue->pop();
    }

    public function testGetsRecurring(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testGetsRecurring();
    }

    public function testGetsDepends(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        parent::testGetsDepends();
    }
}

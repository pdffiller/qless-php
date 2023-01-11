<?php

namespace Qless\Tests\Tags;

use Qless\Exceptions\UnsupportedMethodException;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Support\LightClientTrait;

class TagsLightTest extends QlessTestCase
{
    use LightClientTrait;

    /**
     * @test
     */
    public function shouldThrowExceptionWhenTryToSetTag(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        $queue = $this->client->queues['test-queue'];
        $queue->put('Foo', [], 'jid');

        $jobs = $queue->jobs->waiting();
        $jobs['jid']->tag('tag');
    }

    /**
     * @test
     */
    public function shouldThrowExceptionWhenTryToRemoveTag(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        $queue = $this->client->queues['test-queue'];
        $queue->put('Foo', [], 'jid');

        $jobs = $queue->jobs->waiting();
        $jobs['jid']->untag('tag');
    }
}

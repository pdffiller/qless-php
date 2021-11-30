<?php

namespace Qless\Tests\Tags;

use Qless\Tests\QlessTestCase;

class TagsTest extends QlessTestCase
{
    public function testPutWithEmptyTags(): void
    {
        $queue = $this->client->queues['test-queue'];
        $queue->put('Foo', [], 'jid-11', 0, 0, 0, ['', '  ', ' ', 'tag-test1', 'tag-test2']);

        self::assertEquals(['tag-test1', 'tag-test2'], $this->client->jobs['jid-11']->tags);
    }

    public function testPutAndTagWithEmptyTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('Foo', [], 'jid-12', 0, 0);
        $queue->pop()->tag('', ' ', '   ', 'tag-test3', 'tag-test4');

        self::assertEquals(['tag-test3', 'tag-test4'], $this->client->jobs['jid-12']->tags);
    }

    public function testPutWithEmptyTagsAndTagWithEmptyTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->put('Foo', [], 'jid-13', 0, 0, 0, ['', 'tag-test5', 'tag-test6', ' ']);
        $queue->pop()->tag('', ' ', '   ', ' tag-test8');

        self::assertEquals(['tag-test5', 'tag-test6', ' tag-test8'], $this->client->jobs['jid-13']->tags);
    }

    public function testRecurWithEmptyTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->recur('Foo', [], 10, null, 'jid', null, null, null, [' ', 'tag-test9', '']);

        self::assertEquals(['tag-test9'], $this->client->jobs['jid']->tags);
    }

    public function testRecurWithEmptyTagsAndTagWithEmptyTags(): void
    {
        $queue = $this->client->queues['test-queue'];

        $queue->recur('Foo', [], 10, null, 'jid', null, null, null, [' ', 'tag-test9', '']);
        $this->client->jobs['jid']->tag('', 'foo', 'bar ', ' ');

        self::assertEquals(['tag-test9', 'foo', 'bar '], $this->client->jobs['jid']->tags);
    }
}

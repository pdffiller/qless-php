<?php

namespace Qless\Tests\Topics;

use Qless\Queues\Collection;
use Qless\Queues\Queue;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Support\RedisAwareTrait;
use Qless\Topics\Topic;

class TopicTest extends QlessTestCase
{
    use RedisAwareTrait;

    /** @test */
    public function shouldGetQueuesBySubscription(): void
    {
        $queues = [];
        for ($i = 1; $i<=2; $i++) {
            $queues[$i] = new Queue('test-queue-' . $i, $this->client);
        }

        $queues[1]->subscribe('big.*.*');
        $queues[2]->subscribe('big.*.apples');

        $queuesCollection = new Collection($this->client);

        $queuesExpected = ['test-queue-1', 'test-queue-2'];

        $queuesGreenApples = $queuesCollection->fromSubscriptions('big.green.apples');
        sort($queuesGreenApples);

        $queuesRedApples = $queuesCollection->fromSubscriptions('big.red.apples');
        sort($queuesRedApples);

        self::assertEquals($queuesExpected, $queuesGreenApples);
        self::assertEquals($queuesExpected, $queuesRedApples);
        self::assertEquals([], $queuesCollection->fromSubscriptions('*.*.oranges'));

        $queues[1]->unsubscribe('big.*.*');
        $queues[2]->unsubscribe('big.*.apples');
    }

    /** @test */
    public function shouldQueueSubscribe(): void
    {
        $queues = [];
        for ($i = 1; $i<=5; $i++) {
            $queues[$i] = new Queue('test-queue-' . $i, $this->client);
        }

        $queues[1]->subscribe('big.*.*');
        $queues[2]->subscribe('big.*.apples');
        $queues[3]->subscribe('*.*.apples');
        $queues[4]->subscribe('*.*.oranges');
        $queues[5]->subscribe('big.#');

        $topic = new Topic('big.green.apples', $this->client);

        $topic->put('Xxx\Yyy', []);

        $job1 = $queues[1]->pop();
        $job2 = $queues[2]->pop();
        $job3 = $queues[3]->pop();
        $job4 = $queues[4]->pop();
        $job5 = $queues[5]->pop();

        self::assertEquals('Xxx\Yyy', $job1->getKlass());
        self::assertEquals('Xxx\Yyy', $job2->getKlass());
        self::assertEquals('Xxx\Yyy', $job3->getKlass());
        self::assertEmpty($job4);
        self::assertEquals('Xxx\Yyy', $job5->getKlass());
    }

    /** @test */
    public function shouldQueueUnSubscribe(): void
    {
        $queues = [];
        for ($i = 1; $i<=3; $i++) {
            $queues[$i] = new Queue('test-queue-' . $i, $this->client);
        }

        $queues[1]->subscribe('big.*.*');
        $queues[1]->subscribe('big.deal.*');
        $queues[2]->subscribe('big.*.apples');
        $queues[3]->subscribe('*.*.apples');

        $queues[2]->unSubscribe('big.*.apples');

        $topic = new Topic('big.green.apples', $this->client);

        $topic->put('Xxx\Yyy', []);

        $job1 = $queues[1]->pop();
        $job2 = $queues[2]->pop();
        $job3 = $queues[3]->pop();

        self::assertEquals('Xxx\Yyy', $job1->getKlass());
        self::assertEmpty($job2);
        self::assertEquals('Xxx\Yyy', $job3->getKlass());
    }
}

<?php

namespace Qless\Tests;

use Qless\Config;
use Qless\Exceptions\QlessException;
use Qless\Exceptions\UnknownPropertyException;
use Qless\Jobs\Collection as JobsCollection;
use Qless\LuaScript;
use Qless\Queues\Queue;
use Qless\Subscribers\WatchdogSubscriber;
use Qless\Tests\Support\RedisAwareTrait;
use Qless\Workers\Collection as WorkersCollection;
use Qless\Queues\Collection as QueuesCollection;

/**
 * Qless\Tests\ClientTest
 *
 * @package Qless\Tests
 */
class ClientTest extends QlessTestCase
{
    use RedisAwareTrait;

    /**
     * @test
     */
    public function shouldGetEmptyWorkersList(): void
    {
        self::assertEquals('{}', $this->client->call('workers'));
    }

    /**
     * @test
     */
    public function shouldGetAWorkersList(): void
    {
        $this->client->pop('test-queue', 'w1', 1);

        self::assertEquals(
            '[{"stalled":0,"name":"w1","jobs":0}]',
            $this->client->call('workers')
        );

        self::assertEquals(
            '{"stalled":{},"jobs":{}}',
            $this->client->call('workers', 'w1')
        );

        self::assertEquals(
            '{"stalled":{},"jobs":{}}',
            $this->client->call('workers', 'w2')
        );


        $this->client->pop('test-queue', 'w3', 1);
        $this->client->pop('test-queue', 'w4', 1);

        $expected =<<<WRK
[{"stalled":0,"name":"w4","jobs":0},{"stalled":0,"name":"w3","jobs":0},{"stalled":0,"name":"w1","jobs":0}]
WRK;

        self::assertEquals(
            $expected,
            $this->client->call('workers')
        );
    }

    /**
     * @test
     */
    public function shouldCreateASubscriber(): void
    {
        self::assertInstanceOf(WatchdogSubscriber::class, $this->client->createSubscriber([]));
    }

    /**
     * @test
     * @dataProvider inaccessiblePropertyDataProvider
     *
     * @param string $property
     * @param $expected
     */
    public function shouldReturnExpectedValueOnMagicGet(string $property, $expected): void
    {
        self::assertInstanceOf($expected, $this->client->{$property});
    }

    public function inaccessiblePropertyDataProvider(): array
    {
        return [
            ['jobs',    JobsCollection::class],
            ['config',  Config::class],
            ['lua',     LuaScript::class],
            ['workers', WorkersCollection::class],
            ['queues',  QueuesCollection::class],
        ];
    }

    /**
     * @test
     */
    public function shouldThrowExceptionWhenGetInaccessibleProperty(): void
    {
        $this->expectExceptionMessage("Getting unknown property: Qless\Client::foo");
        $this->expectException(UnknownPropertyException::class);
        $this->client->foo;
    }

    /**
     * @test
     * @dataProvider popDataProvider
     *
     * @param string $qName
     * @param string $jName
     * @param string $wName
     * @param string $cName
     * @param int    $expires
     * @param array  $data
     *
     * @throws QlessException
     */
    public function shouldGetTheNextJobOnTheDesiredQueue(
        string $qName,
        string $jName,
        string $wName,
        string $cName,
        int $expires,
        array $data
    ): void {
        $queue = new Queue($qName, $this->client);
        $queue->put($cName, $data, $jName);

        $actual = $this->client->pop($qName, $wName, 1);

        self::assertIsString($actual);
        self::assertNotEmpty($actual);

        $actual = json_decode($actual, true);

        self::assertIsArray($actual);
        self::assertCount(1, $actual);

        $actual = $actual[0];

        self::assertArrayHasKey('expires', $actual);

        self::assertGreaterThan($expires, $actual['expires']);
        self::assertLessThan($expires + 2, $actual['expires']);

        $heartbeat = 60;
        $realDate  = (int) ($actual['expires'] - $heartbeat);

        $actual['expires'] = $expires;

        self::assertEquals(
            $this->getExpectedJob($jName, $cName, $qName, $wName, $expires, $realDate, $data),
            [$actual]
        );
    }

    /**
     * @test
     */
    public function shouldReturnEmptyStringWhenJobDoesNotExist(): void
    {
        self::assertEquals('{}', $this->client->pop('non-existent-queue', 'worker-1', 1));
    }

    public function popDataProvider(): array
    {
        return [
            [
                'test-queue-' . random_int(1, 99),
                'job-' . random_int(1, 99),
                'worker-' . random_int(1, 99),
                'Xxx\Yyy',
                time() + 60,
                ['performMethod' => 'myPerformMethod', 'payload' => 'message-' . random_int(1, 99)],
            ]
        ];
    }

    protected function getExpectedJob(
        string $jName,
        string $cName,
        string $qName,
        string $wName,
        int $expires,
        int $realDate,
        array $data
    ): array {
        return [
            [
                'jid'          => $jName,
                'retries'      => 5,
                'data'         => json_encode($data),
                'failure'      => [],
                'expires'      => $expires,
                'remaining'    => 5,
                'klass'        => $cName,
                'tracked'      => false,
                'tags'         => [],
                'queue'        => $qName,
                'state'        => 'running',
                'history'      => [
                    [
                        'when'   => $realDate,
                        'q'      => $qName,
                        'what'   => 'put',
                    ],
                    [
                        'when'   => $realDate,
                        'what'   => 'popped',
                        'worker' => $wName,
                    ],
                ],
                'dependencies' => [],
                'dependents'   => [],
                'priority'     => 0,
                'worker'       => $wName,
                'spawned_from_jid' => false

            ]
        ];
    }

    /**
     * @test
     */
    public function shouldRetrieveStats(): void
    {
        $queue = new Queue('some-queue', $this->client);
        $queue->put('Xxx\Yyy', ['some-data']);

        $stats = $this->client->stats('some-queue', time());

        self::assertNotEmpty($stats);
        self::assertIsString($stats);

        $stats = json_decode($stats, true);

        self::assertArrayHasKey('failed', $stats);
        self::assertArrayHasKey('retries', $stats);
        self::assertArrayHasKey('failures', $stats);
        self::assertArrayHasKey('wait', $stats);
        self::assertArrayHasKey('run', $stats);

        self::assertCount(5, $stats);

        self::assertIsArray($stats['wait']);
        self::assertCount(4, $stats['wait']);

        self::assertIsArray($stats['run']);
        self::assertCount(4, $stats['run']);
    }

    /**
     * @test
     */
    public function shouldPauseTheQueue(): void
    {
        $queue = new Queue('some-queue', $this->client);
        $queue->put('Xxx\Yyy', ['some-data'], 'job-42');

        self::assertFalse($queue->isPaused());

        $this->client->pause('some-queue');
        self::assertTrue($queue->isPaused());

        $this->client->unpause('some-queue');
        self::assertFalse($queue->isPaused());
    }

    /**
     * @test
     */
    public function shouldGetJob(): void
    {
        $queue = new Queue('some-queue', $this->client);
        $queue->put('Xxx\Yyy', ['some-data'], 'job-42');

        $actual = $this->client->get('job-42');

        self::assertNotEmpty($actual);
        self::assertJson($actual);

        self::assertNull($this->client->get('job-43'));
    }

    /**
     * @test
     */
    public function shouldCorrectDetermineLength(): void
    {
        $queue = new Queue('some-queue-2', $this->client);

        self::assertEquals(0, $this->client->length('some-queue-2'));

        $queue->put('Xxx\Yyy', ['some-data'], 'job-42');
        self::assertEquals(1, $this->client->length('some-queue-2'));

        $queue->pop()->complete();

        self::assertEquals(0, $this->client->length('some-queue-2'));
        self::assertEquals(0, $this->client->length('some-queue-3'));
    }

    /**
     * @test
     */
    public function shouldThrowExpectedExceptionOnCompleteRunningJob(): void
    {
        $this->expectExceptionMessage("Job job-42 is not currently running: waiting");
        $this->expectException(QlessException::class);
        $queue = new Queue('some-queue', $this->client);
        $queue->put('Xxx\Yyy', ['some-data'], 'job-42');

        $this->client->complete('job-42', 'worker-1', 'some-queue', '{}');
    }

    /**
     * @test
     */
    public function shouldThrowExpectedExceptionOnCompleteNonExistingJob(): void
    {
        $this->expectExceptionMessage("Job job-43 does not exist");
        $this->expectException(QlessException::class);
        $queue = new Queue('some-queue', $this->client);
        $queue->put('Xxx\Yyy', ['some-data'], 'job-43');

        $queue->pop()->cancel();

        $this->client->complete('job-43', 'worker-1', 'some-queue', '{}');
    }

    /**
     * @test
     */
    public function shouldCompleteJob(): void
    {
        $queue = new Queue('some-queue', $this->client);
        $queue->put('Xxx\Yyy', ['some-data'], 'job-44');

        $this->client->pop('some-queue', 'worker-1', 1);

        self::assertEquals(
            'complete',
            $this->client->complete('job-44', 'worker-1', 'some-queue', '{}')
        );
    }

    /**
     * @test
     */
    public function shouldReturnAListOfQueues(): void
    {
        $this->client->flush();

        (new Queue('some-queue-1', $this->client))->put('Xxx\Yyy', ['some-data'], 'job-44');
        (new Queue('some-queue-2', $this->client))->put('Xxx\Yyy', ['some-data'], 'job-44');

        $queue = $this->client->queues('some-queue-1');

        self::assertJson($queue);
        self::assertNotEmpty($queue);

        $expected1 = [
            'paused'    => false,
            'running'   => 0,
            'name'      => 'some-queue-1',
            'waiting'   => 0,
            'recurring' => 0,
            'depends'   => 0,
            'stalled'   => 0,
            'scheduled' => 0,
        ];

        self::assertEquals($expected1, json_decode($queue, true));

        $expected2 = [
            'paused'    => false,
            'running'   => 0,
            'name'      => 'some-queue-100',
            'waiting'   => 0,
            'recurring' => 0,
            'depends'   => 0,
            'stalled'   => 0,
            'scheduled' => 0,
        ];

        $queue = $this->client->queues('some-queue-100');

        self::assertJson($queue);
        self::assertNotEmpty($queue);

        self::assertEquals($expected2, json_decode($queue, true));

        $expected3 = [
            [
                'paused'    => false,
                'running'   => 0,
                'name'      => 'some-queue-1',
                'waiting'   => 0,
                'recurring' => 0,
                'depends'   => 0,
                'stalled'   => 0,
                'scheduled' => 0,
            ],
            [
                'paused'    => false,
                'running'   => 0,
                'name'      => 'some-queue-2', // NOTE: not some-queue-100
                'waiting'   => 1,
                'recurring' => 0,
                'depends'   => 0,
                'stalled'   => 0,
                'scheduled' => 0,
            ],
        ];

        self::assertEquals($expected3, json_decode($this->client->queues(), true));
    }

    /**
     * @test
     */
    public function shouldThrowExceptionWhenTimeoutFail(): void
    {
        $this->expectExceptionMessage("Job foo does not exist");
        $this->expectException(QlessException::class);
        $this->client->timeout('foo');
    }

    /**
     * @test
     */
    public function shouldGetWaitingList(): void
    {
        $queueName = uniqid('waiting_test_', false);
        $jobId = uniqid('job-', false);

        (new Queue($queueName, $this->client))->put('Xxx\Yyy', ['test' => 'some-data'], $jobId);
        $jobIds = $this->client->jobs('waiting', $queueName);

        self::assertEquals($jobId, array_pop($jobIds));
    }

    /**
     * @test
     */
    public function shouldGetCompletedList(): void
    {
        $queueName = uniqid('completed_test_', false);
        $jobId = uniqid('job-', false);

        $queue = new Queue($queueName, $this->client);

        $queue->put('Xxx\Yyy', ['test' => 'some-data'], $jobId);

        $job = $queue->popByJid($jobId);

        $job->complete();

        $jobIds = $this->client->jobs('complete', $queueName);

        self::assertEquals($jobId, array_pop($jobIds));
    }

    public function failedJobsTtlProvider(): array
    {
        return [    // ttl, keep job?
            'no ttl' => [0, false],
            'ttl exists' => [100, true],
        ];
    }

    /**
     * @dataProvider failedJobsTtlProvider
     * @test
     * @param int $ttl
     * @param bool $keepJob
     */
    public function removeFailedJobsByTtl(int $ttl, bool $keepJob): void
    {
        $queue = $this->client->queues['test-queue'];
        $this->client->config->set('jobs-failed-history', $ttl);

        $queue->put('SampleHandler', [], 'my-test-jid-failed');

        $job = $queue->popByJid('my-test-jid-failed');
        $job->fail('test', 'Testing');

        $actualJob = $this->client->jobs->get('my-test-jid-failed');

        if ($keepJob === true) {
            $this->assertNotNull($actualJob);
        } else {
            $this->assertNull($actualJob);
        }
    }
}

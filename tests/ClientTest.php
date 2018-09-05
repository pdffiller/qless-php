<?php

namespace Qless\Tests;

use Qless\Config;
use Qless\Jobs;
use Qless\Lua;
use Qless\Queue;
use Qless\Tests\Support\RedisAwareTrait;

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
     * @dataProvider inaccessiblePropertyDataProvider
     *
     * @param string $property
     * @param $expected
     */
    public function shouldReturnExpectedValueOnMagicGet(string $property, $expected)
    {
        if ($expected === null) {
            $this->assertSame($expected, $this->client->{$property});
        } else {
            $this->assertInstanceOf($expected, $this->client->{$property});
        }
    }

    public function inaccessiblePropertyDataProvider()
    {
        return [
            ['foo',    null],
            ['job',    null],
            ['jobs',   Jobs::class],
            ['config', Config::class],
            ['lua',    Lua::class],
        ];
    }

    /**
     * @test
     * @covers \Qless\Client::pop
     * @dataProvider popDataProvider
     *
     * @param string $qName
     * @param string $jName
     * @param string $wName
     * @param string $cName
     * @param int    $expires
     * @param array  $data
     *
     * @throws \Qless\QlessException
     */
    public function shouldGetTheNextJobOnTheDesiredQueue(
        string $qName,
        string $jName,
        string $wName,
        string $cName,
        int $expires,
        array $data
    ) {
        $queue = new Queue($qName, $this->client);
        $queue->put($cName, $jName, $data);

        $actual = $this->client->pop($qName, $wName, 1);

        $this->assertTrue(is_string($actual));
        $this->assertNotEmpty($actual);

        $actual = json_decode($actual, true);

        $this->assertTrue(is_array($actual));
        $this->assertCount(1, $actual);

        $actual = $actual[0];

        $this->assertArrayHasKey('expires', $actual);

        $this->assertGreaterThan($expires, $actual['expires']);
        $this->assertLessThan($expires + 2, $actual['expires']);

        $heartbeat = 60;
        $realDate  = intval($actual['expires'] - $heartbeat);

        $actual['expires'] = $expires;

        $this->assertEquals(
            $this->getExpectedJob($jName, $cName, $qName, $wName, $expires, $realDate, $data),
            [$actual]
        );
    }

    /** @test */
    public function shouldReturnEmptyStringWhenJobDoesNotExist()
    {
        $this->assertEquals('{}', $this->client->pop('non-existent-queue', 'worker-1', 1));
    }

    public function popDataProvider()
    {
        return [
            [
                'test-queue-' . mt_rand(1, 99),
                'job-' . mt_rand(1, 99),
                'worker-' . mt_rand(1, 99),
                'Xxx\Yyy',
                time() + 60,
                ['performMethod' => 'myPerformMethod', 'payload' => 'message-' . mt_rand(1, 99)],
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
    ) {
        return [
            [
                'jid'          => $jName,
                'retries'      => 5,
                'data'         => json_encode($data),
                'failure'      => [],
                'expires'      => $expires,
                'remaining'    => 5,
                'interval'     => 0,
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
                'result_data'  => [],
                'resources'    => [],
                'priority'     => 0,
                'worker'       => $wName,

            ]
        ];
    }
}

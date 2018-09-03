<?php

namespace Qless\Tests;

use Qless\Queue;
use Qless\Tests\Support\RedisAwareTrait;

/**
 * Qless\Tests\QueuePerformanceTest
 *
 * @group performance
 * @package Qless\Tests
 */
class QueuePerformanceTest extends QlessTestCase
{
    use RedisAwareTrait;

    const TEST_TIME = 2;

    protected static $report = [];

    public function testPerfPuttingJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->put("Sample\\TestWorkerImpl", $e, []);
        });

        $cb(self::TEST_TIME, __METHOD__);
    }

    public function testPerfPuttingThenPoppingJobs()
    {
        $queue = new Queue("testQueue", $this->client);
        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->put("Sample\\TestWorkerImpl", $e, []);
        });

        $cb(self::TEST_TIME, __METHOD__);

        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->pop('worker');
        });
        $cb(self::TEST_TIME, __METHOD__);
    }

    public function testPerfDirectRedis()
    {
        $redis = $this->redis();

        $cb = $this->getProfilerForCallback(function ($e) use ($redis) {
            $redis->hSet($e, 'data', '');
        });

        $cb(self::TEST_TIME, __METHOD__);
    }

    public function testPerfQueueLength()
    {
        $queue = new Queue("testQueue", $this->client);
        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->length();
        });

        $cb(self::TEST_TIME, __METHOD__);
    }

    protected function getProfilerForCallback(\Closure $cb)
    {
        return function ($time, $message = '') use ($cb) {
            $s = microtime(true);
            $e = microtime(true) - $s;
            $i = 0;

            while ($e < $time) {
                $cb($e);
                ++$i;
                $e = microtime(true) - $s;
            }

            $message = str_replace('Qless\Tests\QueuePerformanceTest::testPerf', '', $message);
            $message = ucfirst(trim(strtolower(implode(' ', preg_split('/(?=[A-Z])/', $message)))));

            self::$report[] = sprintf(
                ' %-30s %d iterations in %.3f seconds, %0.3f / sec',
                ($message ? $message . ':' : ''),
                $i,
                $e,
                $i / $e
            );
        };
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$report = [];
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public static function tearDownAfterClass()
    {
        fprintf(STDOUT, PHP_EOL . PHP_EOL);
        foreach (self::$report as $line) {
            fprintf(STDOUT, $line . PHP_EOL);
        }
    }
}

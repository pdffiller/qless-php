<?php

require_once __DIR__ . '/QlessTest.php';

/**
 * @group performance
 */
class QueuePerformanceTest extends QlessTest {

    const TEST_TIME = 2;

    public function testPerfPuttingJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->put("Sample\\TestWorkerImpl", $e, []);
        });

        $cb(self::TEST_TIME, __METHOD__);
    }

    public function testPerfPuttingThenPoppingJobs() {
        $queue = new Qless\Queue("testQueue", $this->client);
        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->put("Sample\\TestWorkerImpl", $e, []);
        });

        $cb(self::TEST_TIME, __METHOD__);

        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->pop('worker');
        });
        $cb(self::TEST_TIME, __METHOD__);
    }

    public function testPerfDirectRedis() {

        $redis = new Redis();
        $redis->connect(self::$REDIS_HOST, self::$REDIS_PORT);

        $cb = $this->getProfilerForCallback(function ($e) use ($redis) {
            $redis->hSet($e, 'data', '');
        });

        $cb(self::TEST_TIME, __METHOD__);
    }

    public function testPerfQueueLength() {

        $queue = new Qless\Queue("testQueue", $this->client);
        $cb = $this->getProfilerForCallback(function ($e) use ($queue) {
            $queue->length();
        });

        $cb(self::TEST_TIME, __METHOD__);

    }

    protected function getProfilerForCallback(\Closure $cb) {
        return function($time, $message='') use ($cb) {
            $s = microtime(true);
            $e = microtime(true) - $s;
            $i = 0;
            while ($e < $time) {
                $cb($e);
                ++$i;
                $e = microtime(true) - $s;
            }

            printf("%s: %d iterations in %.2f seconds, %0.2f / sec\n", $message, $i, $e, $i / $e);
        };
    }
}
 
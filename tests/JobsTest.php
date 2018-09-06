<?php

namespace Qless\Tests;

use Qless\Queue;

/**
 * Qless\Tests\JobsTest
 *
 * @package Qless\Tests
 */
class JobsTest extends QlessTestCase
{
    public function testItReturnsNullForInvalidJobID()
    {
        $j = $this->client->jobs['xxx'];

        $this->assertNull($j);
    }

    public function testItReturnsExistingJob()
    {
        $this->put('j-1');
        $j = $this->client->jobs['j-1'];

        $this->assertNotNull($j);
        $this->assertEquals('j-1', $j->getId());
    }

    public function testItReturnsExistingJobsKeyedByJobIdentifier()
    {
        $this->put('j-1');
        $this->put('j-2');

        $j = $this->client->jobs->multiget(['j-1', 'j-2']);
        $this->assertCount(2, $j);
        $this->assertArrayHasKey('j-1', $j);
        $this->assertArrayHasKey('j-2', $j);
    }

    public function testItReturnsNoCompletedJobsWhenNoneExist()
    {
        $this->put('j-1');
        $this->put('j-2');

        $j = $this->client->jobs->completed();
        $this->assertEmpty($j);
    }

    public function testItReturnsCompletedJobs()
    {
        $this->put('j-1');
        $this->put('j-2');
        $q  = new Queue('q-1', $this->client);
        $q->pop('w-1')[0]->complete();
        $q->pop('w-1')[0]->complete();

        $j = $this->client->jobs->completed();
        sort($j);
        $this->assertEquals(['j-1', 'j-2'], $j);
    }

    public function testItReturnsFailedJobs()
    {
        $this->put('j-1');
        $this->put('j-2');
        $this->put('j-3');
        $this->put('j-4');
        $q  = new Queue('q-1', $this->client);
        $q->pop('w-1')[0]->fail('system', 'msg');
        $q->pop('w-1')[0]->fail('system', 'msg');
        $q->pop('w-1')[0]->fail('system', 'msg');
        $q->pop('w-1')[0]->fail('main', 'msg');

        $j = $this->client->jobs->failed();
        $this->assertEquals(3, $j['system']);
        $this->assertEquals(1, $j['main']);
    }

    /**
     * @depends testItReturnsFailedJobs
     */
    public function testItReturnsFailedBySpecificGroup()
    {
        $this->put('j-1');
        $this->put('j-2');
        $this->put('j-3');
        $this->put('j-4');
        $q  = new Queue('q-1', $this->client);
        $q->pop('w-1')[0]->fail('system', 'msg');
        $q->pop('w-1')[0]->fail('system', 'msg');
        $q->pop('w-1')[0]->fail('system', 'msg');
        $q->pop('w-1')[0]->fail('main', 'msg');

        $j = $this->client->jobs->failedForGroup('system');

        $this->assertCount(3, $j['jobs']);
    }

    public function testItReturnsRunningJob()
    {
        $this->put('j-1');
        $this->put('j-2');

        $q  = new Queue('q-1', $this->client);
        $q->pop('w-1');

        $j = $this->client->jobs['j-1'];
        $this->assertNotNull($j);
    }

    private function put($jid, $opts = [])
    {
        $opts = array_merge([
            'data'     => [],
            'delay'    => 0,
            'tags'     => [],
            'priority' => 0,
            'retries'  => 0,
            'interval' => 0,
        ], $opts);

        $this->client->put(
            '',
            'q-1',
            $jid,
            'k',
            json_encode($opts['data'], JSON_UNESCAPED_SLASHES),
            $opts['delay'],
            'tags',
            json_encode($opts['tags'], JSON_UNESCAPED_SLASHES),
            'priority',
            $opts['priority'],
            'retries',
            $opts['retries'],
            'interval',
            $opts['interval']
        );
    }
}

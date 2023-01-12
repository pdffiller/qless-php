<?php

namespace Qless\Tests;

class SwitchClientScriptTest extends QlessTestCase
{
    /**
     * @test
     */
    public function shouldPopSameJobAfterScriptSwitch(): void
    {
        $this->client->getLua()->useBaseScript();

        $queue = $this->client->queues['test-queue'];
        $queue->put('Foo', [], 'jid1');

        $this->client->getLua()->useLightScript();

        $job1 = $queue->pop();

        $queue->put('Bar', [], 'jid2');

        $this->client->getLua()->useBaseScript();

        $job2 = $queue->pop();

        $this->assertSame('jid1', $job1->jid);
        $this->assertSame('jid2', $job2->jid);
    }
}

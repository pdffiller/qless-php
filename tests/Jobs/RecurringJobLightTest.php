<?php

namespace Qless\Tests\Jobs;

use Qless\Exceptions\UnsupportedMethodException;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Support\LightClientTrait;

class RecurringJobLightTest extends QlessTestCase
{
    use LightClientTrait;

    /**
     * @test
     */
    public function shouldThrowExceptionWhenTryToAddRecurringJob(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        $this->client->queues['test-queue']->recur('Foo', [], null, null, 'jid');
    }

    /**
     * @test
     */
    public function shouldThrowExceptionWhenTryToRemoveRecurringJob(): void
    {
        $this->expectException(UnsupportedMethodException::class);

        $this->client->queues['test-queue']->unrecur('jid');
    }
}

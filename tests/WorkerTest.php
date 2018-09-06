<?php

namespace Qless\Tests;

use Qless\Client;
use Qless\Worker;
use Qless\Tests\Stubs\WorkerStub;

/**
 * Qless\Tests\WorkerTest
 *
 * @package Qless\Tests
 */
class WorkerTest extends QlessTestCase
{
    /** @test */
    public function shouldRegisterJobPerformHandler()
    {
        $worker = new Worker('test', [], $this->createMock(Client::class));

        $reflection = new \ReflectionObject($worker);

        $jobPerformClass = $reflection->getProperty('jobPerformClass');
        $jobPerformClass->setAccessible(true);

        $worker->registerJobPerformHandler(WorkerStub::class);
        $this->assertEquals(WorkerStub::class, $jobPerformClass->getValue($worker));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function shouldThrowExceptionInCaseOfInvalidJobClass()
    {
        $this->expectExceptionMessage(
            'Provided Job class "stdClass" does not implement Qless\Jobs\JobHandlerInterface interface.'
        );
        $worker = new Worker('test', [], $this->createMock(Client::class));
        $worker->registerJobPerformHandler(\stdClass::class);
    }

    /**
     * @test
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not find job perform class FooBar.
     */
    public function shouldThrowExceptionInCaseOfNonExistentJobClass()
    {
        $worker = new Worker('test', [], $this->createMock(Client::class));
        $worker->registerJobPerformHandler('FooBar');
    }
}

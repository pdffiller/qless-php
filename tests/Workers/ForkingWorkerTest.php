<?php

namespace Qless\Tests\Workers;

use Qless\Client;
use Qless\Jobs\Reservers\OrderedReserver;
use Qless\Tests\QlessTestCase;
use Qless\Tests\Stubs\WorkerStub;
use Qless\Workers\ForkingWorker;

/**
 * Qless\Tests\Workers\ForkingWorkerTest
 *
 * @package Qless\Tests
 */
class ForkingWorkerTest extends QlessTestCase
{
    /** @test */
    public function shouldRegisterJobPerformHandler()
    {
        $worker = new ForkingWorker(
            $this->createMock(OrderedReserver::class),
            $this->createMock(Client::class)
        );

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

        $worker = new ForkingWorker(
            $this->createMock(OrderedReserver::class),
            $this->createMock(Client::class)
        );

        $worker->registerJobPerformHandler(\stdClass::class);
    }

    /**
     * @test
     * @expectedException \RuntimeException
     * @expectedExceptionMessage Could not find job perform class FooBar.
     */
    public function shouldThrowExceptionInCaseOfNonExistentJobClass()
    {
        $worker = new ForkingWorker(
            $this->createMock(OrderedReserver::class),
            $this->createMock(Client::class)
        );

        $worker->registerJobPerformHandler('FooBar');
    }
}

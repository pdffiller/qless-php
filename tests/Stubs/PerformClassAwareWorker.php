<?php

namespace Qless\Tests\Stubs;

use Qless\Events\User\Job as JobEvent;
use Qless\Workers\AbstractWorker;

/**
 * Qless\Tests\Stubs\PerformClassAwareWorker
 *
 * @package Qless\Tests\Stubs
 */
class PerformClassAwareWorker extends AbstractWorker
{
    public function perform(): void
    {
        if (!$this->jobPerformHandler) {
            throw new \RuntimeException('Job handler not set');
        }

        $job = $this->reserve();

        if (method_exists($this->jobPerformHandler, 'setUp')) {
            $this->jobPerformHandler->setUp();
        }

        $this->getEventsManager()->fire(new JobEvent\BeforePerform($this->jobPerformHandler, $job));
        $this->jobPerformHandler->perform($job);
        $this->getEventsManager()->fire(new JobEvent\AfterPerform($this->jobPerformHandler, $job));
    }
}

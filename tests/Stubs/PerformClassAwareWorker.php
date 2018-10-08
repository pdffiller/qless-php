<?php

namespace Qless\Tests\Stubs;

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

        $this->getEventsManager()->fire('job:beforePerform', $this->jobPerformHandler, [$job->jid]);
        $this->jobPerformHandler->perform($job);
        $this->getEventsManager()->fire('job:afterPerform', $this->jobPerformHandler, [$job->jid]);
    }
}

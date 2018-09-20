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
        $job = $this->reserve();

        $handler = $this->getPerformHandlerFactory()->create($this->jobPerformClass);

        if (method_exists($handler, 'setUp')) {
            $handler->setUp();
        }

        $this->getEventsManager()->fire('job:beforePerform', $handler, [$job->jid]);
        $handler->perform($job);
        $this->getEventsManager()->fire('job:afterPerform', $handler, [$job->jid]);
    }
}

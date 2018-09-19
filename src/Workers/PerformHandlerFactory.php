<?php

namespace Qless\Workers;

use Qless\EventsManagerAwareInterface;
use Qless\Jobs\JobHandlerInterface;
use Qless\EventsManagerAwareTrait;

final class PerformHandlerFactory implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

    /**
     * Creates a job perform handler.
     *
     * @param  string $className
     * @return JobHandlerInterface|EventsManagerAwareInterface|object
     */
    public function create(string $className)
    {
        /** @var JobHandlerInterface|EventsManagerAwareInterface|object $handler */
        $handler = new $className();

        if ($handler instanceof EventsManagerAwareInterface) {
            $handler->setEventsManager($this->getEventsManager());
        }

        return $handler;
    }
}

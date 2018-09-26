<?php

namespace Qless\Jobs;

use Qless\EventsManager;
use Qless\EventsManagerAwareInterface;

/**
 * Qless\Jobs\PerformHandlerFactory
 *
 * @package Qless\Jobs
 */
final class PerformHandlerFactory
{
    /**
     * Creates a job perform handler.
     *
     * @param  string        $className
     * @param  EventsManager $eventsManager
     * @return PerformAwareInterface
     */
    public function create(string $className, EventsManager $eventsManager): PerformAwareInterface
    {
        $handler = new $className();

        if ($handler instanceof EventsManagerAwareInterface) {
            $handler->setEventsManager($eventsManager);
        }

        /** @var PerformAwareInterface|EventsManagerAwareInterface $handler */
        return $handler;
    }
}

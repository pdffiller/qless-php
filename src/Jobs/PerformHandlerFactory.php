<?php

namespace Qless\Jobs;

use Qless\EventsManagerAwareInterface;
use Qless\EventsManagerAwareTrait;

/**
 * Qless\Jobs\PerformHandlerFactory
 *
 * @package Qless\Jobs
 */
final class PerformHandlerFactory implements EventsManagerAwareInterface
{
    use EventsManagerAwareTrait;

    /**
     * Creates a job perform handler.
     *
     * @param  string $className
     * @return PerformAwareInterface
     */
    public function create(string $className): PerformAwareInterface
    {
        $handler = new $className();

        if ($handler instanceof EventsManagerAwareInterface) {
            $handler->setEventsManager($this->getEventsManager());
        }

        /** @var PerformAwareInterface|EventsManagerAwareInterface $handler */
        return $handler;
    }
}

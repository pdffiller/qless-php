<?php

namespace Qless;

/**
 * Qless\EventsManagerAwareInterface
 *
 * @package Qless
 */
interface EventsManagerAwareInterface
{
    /**
     * Gets internally used Events Manager.
     *
     * @return EventsManager
     */
    public function getEventsManager(): EventsManager;

    /**
     * Sets internally used Events Manager.
     *
     * @param  EventsManager $manager
     * @return void
     */
    public function setEventsManager(EventsManager $manager): void;
}

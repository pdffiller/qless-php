<?php

namespace Qless;

/**
 * Qless\EventsManagerAwareTrait
 *
 * @package Qless
 */
trait EventsManagerAwareTrait
{
    /** @var EventsManager */
    private $eventsManager;

    /**
     * {@inheritdoc}
     *
     * @return EventsManager
     */
    final public function getEventsManager(): EventsManager
    {
        if ($this->eventsManager === null) {
            $this->eventsManager = new EventsManager();
        }

        return $this->eventsManager;
    }

    /**
     * {@inheritdoc}
     *
     * @param  EventsManager $manager
     * @return void
     */
    final public function setEventsManager(EventsManager $manager): void
    {
        $this->eventsManager = $manager;
    }
}

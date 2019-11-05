<?php

namespace Qless;

use SplPriorityQueue;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Events\User\AbstractEvent as AbstractUserEvent;

/**
 * Qless\EventsManager
 *
 * @package Qless
 */
final class EventsManager
{
    /** @var SplPriorityQueue[] */
    private $events = [];

    /**
     * Attach a listener to the events manager.
     *
     * @param  string          $eventName
     * @param  object|callable $handler
     * @param  int             $priority
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function attach(string $eventName, $handler, int $priority = 100): void
    {
        if (is_object($handler) == false && is_callable($handler) == false) {
            throw new InvalidArgumentException(
                sprintf('Event handler must be either an object or a callable %s given.', gettype($handler))
            );
        }

        $priorityQueue = $this->fetchQueue($eventName);
        $priorityQueue->insert($handler, $priority);
    }

    /**
     * Fetches a priority events queue by event type.
     *
     * @param  string $eventName
     * @return SplPriorityQueue
     */
    protected function fetchQueue(string $eventName): SplPriorityQueue
    {
        if (isset($this->events[$eventName]) == false) {
            $this->events[$eventName] = $this->createQueue();
        }

        return $this->events[$eventName];
    }

    /**
     * Creates a priority events queue.
     *
     * @return SplPriorityQueue
     */
    protected function createQueue(): SplPriorityQueue
    {
        $priorityQueue = new SplPriorityQueue();
        $priorityQueue->setExtractFlags(SplPriorityQueue::EXTR_DATA);

        return $priorityQueue;
    }

    /**
     * Detach the listener from the events manager.
     *
     * @param  string          $eventName
     * @param  object|callable $handler
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function detach(string $eventName, $handler): void
    {
        if (is_object($handler) == false && is_callable($handler) == false) {
            throw new InvalidArgumentException(
                sprintf('Event handler must be either an object or a callable %s given.', gettype($handler))
            );
        }

        if (isset($this->events[$eventName]) == false) {
            return;
        }

        $priorityQueue = $this->events[$eventName];
        $priorityQueue->setExtractFlags(SplPriorityQueue::EXTR_BOTH);
        $priorityQueue->top();

        $newPriorityQueue = $this->createQueue();

        while ($priorityQueue->valid()) {
            $data = $priorityQueue->current();
            $priorityQueue->next();

            if ($data['data'] !== $handler) {
                $newPriorityQueue->insert($data['data'], $data['priority']);
            }
        }

        $this->events[$eventName] = $newPriorityQueue;
    }

    /**
     * Fires an event in the events manager causing the active listeners to be notified about it.
     *
     * @param AbstractUserEvent $event
     * @return mixed|null
     */
    public function fire(AbstractUserEvent $event)
    {
        $status = null;

        $type = $event::getEntityName();
        if (isset($this->events[$type])) {
            $queue = $this->events[$type];
            $status = $this->fireQueue($queue, $event);
        }

        $eventName = $event->getName();
        if (isset($this->events[$eventName])) {
            $queue = $this->events[$eventName];
            $status = $this->fireQueue($queue, $event);
        }

        return $status;
    }

    /**
     * Internal handler to call a queue of events.
     *
     * @param  SplPriorityQueue $queue
     * @param  AbstractUserEvent $event
     * @return mixed|null
     */
    private function fireQueue(SplPriorityQueue $queue, AbstractUserEvent $event)
    {
        $eventHappening = $event::getHappening();

        $iterator = clone $queue;
        $iterator->top();

        $arguments = null;
        $status = null;

        while ($iterator->valid()) {
            $handler = $iterator->current();
            $iterator->next();

            if (is_callable($handler)) {
                $arguments = $arguments ?: [$event];
                $status = call_user_func_array($handler, $arguments);
            } elseif (is_object($handler) && method_exists($handler, $eventHappening)) {
                $status = $handler->{$eventHappening}($event);
            }
        }

        return $status;
    }
}

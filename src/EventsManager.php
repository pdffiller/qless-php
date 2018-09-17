<?php

namespace Qless;

use SplPriorityQueue;
use Qless\Exceptions\InvalidArgumentException;
use Qless\Events\UserEvent;

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
     * @param  string          $event
     * @param  object|callable $handler
     * @param  int             $priority
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function attach(string $event, $handler, int $priority = 100): void
    {
        if (is_object($handler) == false && is_callable($handler) == false) {
            throw new InvalidArgumentException(
                sprintf('Event handler must be either an object or a callable %s given.', gettype($handler))
            );
        }

        $priorityQueue = $this->fetchQueue($event);
        $priorityQueue->insert($handler, $priority);
    }

    /**
     * Fetches a priority events queue by event type.
     *
     * @param  string $event
     * @return SplPriorityQueue
     */
    protected function fetchQueue(string $event): SplPriorityQueue
    {
        if (isset($this->events[$event]) == false) {
            $this->events[$event] = $this->createQueue();
        }

        return $this->events[$event];
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
     * @param  string          $event
     * @param  object|callable $handler
     * @return void
     *
     * @throws InvalidArgumentException
     */
    public function detach(string $event, $handler): void
    {
        if (is_object($handler) == false && is_callable($handler) == false) {
            throw new InvalidArgumentException(
                sprintf('Event handler must be either an object or a callable %s given.', gettype($handler))
            );
        }

        if (isset($this->events[$event]) == false) {
            return;
        }

        $priorityQueue = $this->events[$event];
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

        $this->events[$event] = $newPriorityQueue;
    }

    /**
     * Fires an event in the events manager causing the active listeners to be notified about it.
     *
     * @param  string $eventName
     * @param  object $source
     * @param  array  $data
     * @return mixed|null
     *
     * @throws InvalidArgumentException
     */
    public function fire(string $eventName, $source, array $data = [])
    {
        if (is_object($source) == false) {
            throw new InvalidArgumentException(
                sprintf('Event provider must be an object %s given.', gettype($source))
            );
        }

        if (strpos($eventName, ':') == false) {
            throw new InvalidArgumentException(
                sprintf('Invalid event name "%s". Valid form is "<group>:<name>".', $eventName)
            );
        }

        $parts = explode(':', $eventName);

        $type = $parts[0];
        $name = $parts[1];

        $status = null;
        $event = null;

        if (isset($this->events[$type])) {
            $queue = $this->events[$type];

            $event = new UserEvent($name, $source, $data);
            $status = $this->fireQueue($queue, $event);
        }

        if (isset($this->events[$eventName])) {
            $queue = $this->events[$type];

            if ($event === null) {
                $event = new UserEvent($name, $source, $data);
            }

            $status = $this->fireQueue($queue, $event);
        }

        return $status;
    }

    /**
     * Internal handler to call a queue of events.
     *
     * @param  SplPriorityQueue $queue
     * @param  UserEvent $event
     * @return mixed|null
     */
    private function fireQueue(SplPriorityQueue $queue, UserEvent $event)
    {
        $eventName = $event->getType();
        $source = $event->getSource();
        $data = $event->getData();

        $iterator = clone $queue;
        $iterator->top();

        $arguments = null;
        $status = null;

        while ($iterator->valid()) {
            $handler = $iterator->current();
            $iterator->next();

            if (is_object($handler)) {
                if ($handler instanceof \Closure) {
                    $arguments = $arguments ?: [$event, $source, $data];
                    $status = call_user_func_array($handler, $arguments);
                } else {
                    if (method_exists($handler, $eventName)) {
                        $status = $handler->{$eventName}($event, $source, $data);
                    }
                }
            }
        }

        return $status;
    }
}

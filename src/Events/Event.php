<?php

namespace Qless\Events;

/**
 * Qless\Events\Event
 *
 * Just a DTO for qless events.
 *
 * @package Qless\Events
 */
final class Event
{
    const LOCK_LOST = 'lock_lost';
    const CANCELED = 'canceled';
    const COMPLETED = 'completed';
    const FAILED = 'failed';
    const PUT = 'put';
    const CONFIG_SET = 'config_set';
    const CONFIG_UNSET = 'config_unset';

    /** @var string */
    private $type;

    /** @var null|string */
    private $jid;

    /** @var null|string */
    private $worker;

    /** @var null|string */
    private $queue;

    /** @var null|string */
    private $toQueue;

    /** @var null|string */
    private $group;

    /** @var null|string */
    private $message;

    /** @var null|string */
    private $option;

    /** @var null|mixed */
    private $value;

    /** @var string[] */
    private $validTypes = [
        Event::LOCK_LOST    => true,
        Event::CANCELED     => true,
        Event::COMPLETED    => true,
        Event::FAILED       => true,
        Event::PUT          => true,
        Event::CONFIG_SET   => true,
        Event::CONFIG_UNSET => true,
    ];

    /**
     * Event constructor.
     *
     * @param string      $type
     * @param string|null $jid
     * @param string|null $worker
     * @param string|null $queue
     * @param string|null $toQueue
     * @param string|null $group
     * @param string|null $message
     * @param string|null $option
     * @param mixed|null  $value
     */
    public function __construct(
        string $type,
        string $jid = null,
        string $worker = null,
        string $queue = null,
        string $toQueue = null,
        string $group = null,
        string $message = null,
        string $option = null,
        $value = null
    ) {
        $this->type = $type;
        $this->jid = $jid;
        $this->worker = $worker;
        $this->queue = $queue;
        $this->toQueue = $toQueue;
        $this->group = $group;
        $this->message = $message;
        $this->option = $option;
        $this->value = $value;
    }

    public function valid():bool
    {
        return isset($this->validTypes[$this->type]);
    }

    public function getJid(): string
    {
        return $this->jid;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Gets the affected worker name (if any).
     *
     * @return null|string
     */
    public function getWorker(): ?string
    {
        return $this->worker;
    }

    /**
     * Gets the affected queue name (if any).
     *
     * @return null|string
     */
    public function getQueue(): ?string
    {
        return $this->queue;
    }

    /**
     * Gets the next queue name (if any).
     *
     * @return null|string
     */
    public function getToQueue(): ?string
    {
        return $this->toQueue;
    }

    /**
     * Gets the event's group (if any).
     *
     * @return null|string
     */
    public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * Gets the event's message (if any).
     *
     * @return null|string
     */
    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * Gets the config's option (for "config_set" and "config_unset" events).
     *
     * @return null|string
     */
    public function getOption(): ?string
    {
        return $this->option;
    }

    /**
     * Gets the config's value (for "config_set" event).
     *
     * @return null|mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}

<?php

namespace Qless\Events;

/**
 * Qless\Events\UserEvent
 *
 * @package Qless\Events
 */
class UserEvent
{
    /** @var string */
    private $type;

    /** @var object */
    private $source;

    /** @var array */
    private $data = [];

    /**
     * UserEvent constructor.
     *
     * @param string $type
     * @param object $source
     * @param array  $data
     */
    public function __construct(string $type, $source, array $data = [])
    {
        $this->type = $type;
        $this->source = $source;
        $this->data = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getSource()
    {
        return $this->source;
    }

    public function getData(): array
    {
        return $this->data;
    }
}

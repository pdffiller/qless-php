<?php

namespace Qless\Events\User;

/**
 * Qless\Events\User\AbstractEvent
 *
 * @package Qless\Events\User
 */
abstract class AbstractEvent
{
    /** @var object */
    private $source;

    abstract public static function getEntityName(): string;

    abstract public static function getHappening(): string;

    /**
     * @param object $source
     */
    public function __construct($source)
    {
        $this->source = $source;
    }

    public static function getName(): string
    {
        return static::getEntityName().':'.static::getHappening();
    }

    public function getSource()
    {
        return $this->source;
    }
}

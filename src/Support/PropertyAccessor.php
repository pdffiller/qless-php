<?php

namespace Qless\Support;

use Qless\Exceptions\InvalidCallException;
use Qless\Exceptions\UnknownPropertyException;

/**
 * Qless\Support\PropertyAccessor
 *
 * @package Qless\Support
 */
trait PropertyAccessor
{
    /**
     * Returns the value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$value = $object->property;`.
     *
     * @param  string $name The property name.
     * @return mixed
     *
     * @throws UnknownPropertyException
     * @throws InvalidCallException
     */
    public function __get(string $name)
    {
        $getter = 'get' . ucfirst($name);
        $setter = 'set' . ucfirst($name);

        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        if (method_exists($this, $setter)) {
            throw new InvalidCallException(
                sprintf('Getting write-only property: %s::%s.', get_class($this), $name)
            );
        }

        throw new UnknownPropertyException(
            sprintf('Getting unknown property: %s::%s.', get_class($this), $name)
        );
    }

    /**
     * Sets value of an object property.
     *
     * Do not call this method directly as it is a PHP magic method that
     * will be implicitly called when executing `$object->property = $value;`.
     *
     * @param string $name  The property name or the event name
     * @param mixed  $value The property value
     *
     * @throws UnknownPropertyException if the property is not defined
     * @throws InvalidCallException if the property is read-only
     */
    public function __set($name, $value)
    {
        $getter = 'get' . ucfirst($name);
        $setter = 'set' . ucfirst($name);


        if (method_exists($this, $setter)) {
            $this->$setter($value);
            return;
        }

        if (method_exists($this, $getter)) {
            throw new InvalidCallException(
                sprintf('Setting read-only property: %s::%s.', get_class($this), $name)
            );
        }

        throw new UnknownPropertyException(
            sprintf('Setting unknown property: %s::%s.', get_class($this), $name)
        );
    }
}

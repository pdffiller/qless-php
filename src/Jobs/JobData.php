<?php

namespace Qless\Jobs;

use ArrayObject;
use JsonSerializable;

/**
 * Qless\Jobs\JobData
 *
 * @package Qless\Jobs
 */
final class JobData extends ArrayObject implements JsonSerializable
{
    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Creates a copy of the ArrayObject (alias for \ArrayObject::getArrayCopy).
     *
     * @see \ArrayObject::getArrayCopy
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->getArrayCopy();
    }
}

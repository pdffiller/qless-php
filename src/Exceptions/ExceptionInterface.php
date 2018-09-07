<?php

namespace Qless\Exceptions;

use Throwable;

/**
 * Qless\Exceptions\ExceptionInterface
 *
 * @package Qless\Exceptions
 */
interface ExceptionInterface extends Throwable
{
    /**
     * Gets current error area.
     *
     * @return string|null
     */
    public function getArea();
}

<?php

namespace Qless\Exceptions;

/**
 * Qless\Exceptions\InvalidArgumentException
 *
 * @package Qless\Exceptions
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
    use AreaAwareTrait;
}

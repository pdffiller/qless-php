<?php

namespace Qless\Exceptions;

use RuntimeException;

/**
 * Qless\Exceptions\InvalidJobException
 *
 * @package Qless\Exceptions
 */
class InvalidJobException extends RuntimeException implements ExceptionInterface
{
    use AreaAwareTrait;
}

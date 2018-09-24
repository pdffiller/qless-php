<?php

namespace Qless\Exceptions;

/**
 * Qless\Exceptions\InvalidJobException
 *
 * @package Qless\Exceptions
 */
class InvalidJobException extends RuntimeException
{
    use AreaAwareTrait;
}

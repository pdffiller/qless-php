<?php

namespace Qless\Exceptions;

/**
 * Qless\Exceptions\LogicException
 *
 * @package Qless\Exceptions
 */
class LogicException extends \LogicException implements ExceptionInterface
{
    use AreaAwareTrait;
}

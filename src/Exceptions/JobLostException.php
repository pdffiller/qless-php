<?php

namespace Qless\Exceptions;

use RuntimeException;

/**
 * Qless\Exceptions\JobLostException
 *
 * @package Qless\Exceptions
 */
class JobLostException extends RuntimeException implements ExceptionInterface
{
    use AreaAwareTrait;
}

<?php

namespace Qless\Exceptions;

use RuntimeException;

/**
 * Qless\Exceptions\QlessException
 *
 * The default exception class for qless-core errors.
 *
 * @package Qless\Exceptions
 */
class QlessException extends RuntimeException implements ExceptionInterface
{
    use AreaAwareTrait;
}

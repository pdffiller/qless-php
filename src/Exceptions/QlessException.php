<?php

namespace Qless\Exceptions;

/**
 * Qless\Exceptions\QlessException
 *
 * The default exception class for qless-core errors.
 *
 * @package Qless\Exceptions
 */
class QlessException extends RuntimeException
{
    use AreaAwareTrait;
}

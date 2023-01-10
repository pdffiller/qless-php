<?php

namespace Qless\Exceptions;

use Throwable;

/**
 * Qless\Exceptions\UnsupportedMethodException
 *
 * @package Qless\Exceptions
 */
class UnsupportedMethodException extends BadMethodCallException
{
    public function __construct(
        $message = '',
        $methodName = null,
        $code = 0,
        Throwable $previous = null
    ) {
        if (!empty($methodName)) {
            $message .= ': ' . $methodName;
        }

        parent::__construct($message, $code, $previous);
    }
}

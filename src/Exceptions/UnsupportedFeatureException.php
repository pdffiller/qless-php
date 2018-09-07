<?php

namespace Qless\Exceptions;

use BadMethodCallException;

/**
 * Qless\Exceptions\UnsupportedFeatureException
 *
 * @package Qless\Exceptions
 */
class UnsupportedFeatureException extends BadMethodCallException implements ExceptionInterface
{
    use AreaAwareTrait;
}

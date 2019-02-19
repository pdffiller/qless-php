<?php

namespace Qless\Exceptions;

use Throwable;

/**
 * Class JobAlreadyFinishedException
 * @package Qless\Exceptions
 */
class JobAlreadyFinishedException extends LogicException
{
    public function __construct(?string $message = null, int $code = 0, Throwable $previous = null)
    {
        $message = $message ?? sprintf('Job already finished');
        parent::__construct($message, $code, $previous);
    }
}

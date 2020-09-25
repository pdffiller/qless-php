<?php

namespace Qless\Exceptions;

/**
 * Qless\Exceptions\ErrorFormatter
 *
 * @package Qless\Exceptions
 */
class ErrorFormatter
{
    private $constants = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        E_ALL => 'E_ALL',
    ];

    /**
     * Tries to get the error constant name by its value.
     *
     * @param  mixed $value
     * @return null|string
     */
    public function constant($value): ?string
    {
        return $this->constants[$value] ?? null;
    }
}

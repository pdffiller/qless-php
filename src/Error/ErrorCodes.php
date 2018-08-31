<?php

namespace Qless\Error;

/**
 * Qless\Error\ErrorCodes
 *
 * @package Qless\Error
 */
class ErrorCodes
{
    const E_ERROR = E_ERROR;
    const E_WARNING = E_WARNING;
    const E_PARSE = E_PARSE;
    const E_NOTICE = E_NOTICE;
    const E_CORE_ERROR = E_CORE_ERROR;
    const E_CORE_WARNING = E_CORE_WARNING;
    const E_COMPILE_ERROR = E_COMPILE_ERROR;
    const E_COMPILE_WARNING = E_COMPILE_WARNING;
    const E_USER_ERROR = E_USER_ERROR;
    const E_USER_WARNING = E_USER_WARNING;
    const E_USER_NOTICE = E_USER_NOTICE;
    const E_STRICT = E_STRICT;
    const E_RECOVERABLE_ERROR = E_RECOVERABLE_ERROR;
    const E_DEPRECATED = E_DEPRECATED;
    const E_USER_DEPRECATED = E_USER_DEPRECATED;
    const E_ALL = E_ALL;

    /**
     * Get error's constant name.
     *
     * @param int $value
     * @return string|null
     */
    public function __invoke($value)
    {
        try {
            $class = new \ReflectionClass(__CLASS__);
            $constants = array_flip($class->getConstants());

            return $constants[$value] ?? null;
            // @codeCoverageIgnoreStart
        } catch (\ReflectionException $e) {
            return null;
            // @codeCoverageIgnoreEnd
        }
    }
}

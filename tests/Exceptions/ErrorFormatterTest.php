<?php

namespace Qless\Tests\Exceptions;

use Qless\Exceptions\ErrorFormatter;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\Exceptions\ErrorFormatterTest
 *
 * @package Qless\Tests\Exceptions
 */
class ErrorFormatterTest extends QlessTestCase
{

    /**
     * @test
     * @dataProvider errorConstantsProvider
     *
     * @param mixed $value
     * @param string $expected
     */
    public function shouldReturnConstantName($value, string $expected): void
    {
        $handler = new ErrorFormatter();

        self::assertEquals($expected, $handler->constant($value));
    }

    /**
     * @test
     */
    public function shouldReturnNullOnNonExistentConstant(): void
    {
        $handler = new ErrorFormatter();

        self::assertNull($handler->constant(7));
        self::assertNull($handler->constant('error'));
    }

    public function errorConstantsProvider(): array
    {
        return [
            [E_ERROR, 'E_ERROR'],
            [(string) E_ERROR, 'E_ERROR'],
            [E_WARNING, 'E_WARNING'],
            [(string) E_WARNING, 'E_WARNING'],
            [E_PARSE, 'E_PARSE'],
            [(string) E_PARSE, 'E_PARSE'],
            [E_NOTICE, 'E_NOTICE'],
            [(string) E_NOTICE, 'E_NOTICE'],
            [E_CORE_ERROR, 'E_CORE_ERROR'],
            [(string) E_CORE_ERROR, 'E_CORE_ERROR'],
            [E_CORE_WARNING, 'E_CORE_WARNING'],
            [(string) E_CORE_WARNING, 'E_CORE_WARNING'],
            [E_COMPILE_ERROR, 'E_COMPILE_ERROR'],
            [(string) E_COMPILE_ERROR, 'E_COMPILE_ERROR'],
            [E_COMPILE_WARNING, 'E_COMPILE_WARNING'],
            [(string) E_COMPILE_WARNING, 'E_COMPILE_WARNING'],
            [E_USER_ERROR, 'E_USER_ERROR'],
            [(string) E_USER_ERROR, 'E_USER_ERROR'],
            [E_USER_WARNING, 'E_USER_WARNING'],
            [(string) E_USER_WARNING, 'E_USER_WARNING'],
            [E_USER_NOTICE, 'E_USER_NOTICE'],
            [(string) E_USER_NOTICE, 'E_USER_NOTICE'],
            [E_STRICT, 'E_STRICT'],
            [(string) E_STRICT, 'E_STRICT'],
            [E_RECOVERABLE_ERROR, 'E_RECOVERABLE_ERROR'],
            [(string) E_RECOVERABLE_ERROR, 'E_RECOVERABLE_ERROR'],
            [E_DEPRECATED, 'E_DEPRECATED'],
            [(string) E_DEPRECATED, 'E_DEPRECATED'],
            [E_USER_DEPRECATED, 'E_USER_DEPRECATED'],
            [(string) E_USER_DEPRECATED, 'E_USER_DEPRECATED'],
            [E_ALL, 'E_ALL'],
            [(string) E_ALL, 'E_ALL'],
        ];
    }
}

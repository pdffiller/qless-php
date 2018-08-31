<?php

namespace Qless\Tests\Error;

use Qless\Error\ErrorCodes;
use Qless\Tests\QlessTestCase;

/**
 * Qless\Tests\ErrorCodesTest
 *
 * @package Qless\Tests
 */
class ErrorCodesTest extends QlessTestCase
{
    /**
     * @test
     * @dataProvider errorConstantsProvider
     * @param mixed $value
     * @param string $expected
     */
    public function shouldReturnConstantName($value, $expected)
    {
        $this->assertEquals($expected, call_user_func(new ErrorCodes(), $value));
    }

    /** @test */
    public function shouldReturnNullOnNonExistentConstant()
    {
        $this->assertNull(call_user_func(new ErrorCodes(), 7));
        $this->assertNull(call_user_func(new ErrorCodes(), 'error'));
    }

    public function errorConstantsProvider()
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

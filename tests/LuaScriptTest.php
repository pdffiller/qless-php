<?php

namespace Qless\Tests;

use Qless\Exceptions\RuntimeException;
use Qless\LuaScript;
use Qless\Tests\Support\RedisAwareTrait;

/**
 * Qless\Tests\ListenerTest
 *
 * @package Qless\Tests
 */
class LuaScriptTest extends QlessTestCase
{
    use RedisAwareTrait;

    private $corePath;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->corePath = dirname(__DIR__) . '/src/qless-core/qless.lua';

        if (file_exists($this->corePath . '.back')) {
            @unlink($this->corePath . '.back');
        }

        if (file_exists($this->corePath)) {
            rename($this->corePath, $this->corePath . '.back');
        }

        parent::setUp();
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (file_exists($this->corePath . '.back') && !file_exists($this->corePath)) {
            rename($this->corePath . '.back', $this->corePath);
        } elseif (file_exists($this->corePath . '.back')) {
            @unlink($this->corePath . '.back');
        }

        parent::tearDown();
    }

    /**
     * @test
     */
    public function shouldThrowExpectedExceptionWhenLuaCoreDoesNotExists(): void {
        // If runs on phpunit/phpunit:^8.5 call new method, else - fallback to old method
        if (method_exists($this, 'expectExceptionMessageMatches')) {
            $this->expectExceptionMessageMatches('~Unable to locate qless-core file at path: .*~');
        } else {
            $this->expectExceptionMessageRegExp('~Unable to locate qless-core file at path: .*~');
        }
        $this->expectException(RuntimeException::class);
        $luaScript = new LuaScript($this->redis());

        $luaScript->run('some-command', []);
    }
}

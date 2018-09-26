<?php

namespace Qless\Tests;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Qless\Client;
use Qless\Jobs\Job;
use Qless\Tests\Support\RedisAwareTrait;

/**
 * Qless\Tests\QlessTestCase
 *
 * Base class for qless-php testing
 *
 * @package Qless\Tests
 */
abstract class QlessTestCase extends TestCase
{
    use RedisAwareTrait;

    /**  @var Client */
    protected $client;

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function setUp(): void
    {
        $config = $this->getRedisConfig();
        $this->client = new Client("redis://{$config['host']}:{$config['port']}/0?timeout={$config['timeout']}");
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->client->flush();
    }

    /**
     * Asserts that a condition is zero.
     *
     * @param mixed  $condition
     * @param string $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function assertZero($condition, string $message = ''): void
    {
        $this->assertEquals(0, $condition, $message);
    }

    /**
     * Asserts that a variable is instance of a Job class.
     *
     * @param mixed  $condition
     * @param string $message
     *
     * @throws ExpectationFailedException
     * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
     */
    public function assertIsJob($condition, string $message = '')
    {
        $this->assertInstanceOf(Job::class, $condition, $message);
    }
}

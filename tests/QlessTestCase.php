<?php

namespace Qless\Tests;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Qless\Client;
use Qless\Jobs\BaseJob;
use Qless\Tests\Support\RedisAwareTrait;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

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

        $parameters = [
            'scheme' => 'tcp',
            'host'   => $config['host'],
            'port'   => $config['port'],
        ];

        $options = [
            'parameters' => [
                'database' => 0,
            ]
        ];

        $this->client = new Client($parameters, $options);
    }

    /**
     * {@inheritdoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->client->flush();
        $this->client->disconnect();
    }

    /**
     * Asserts that a condition is zero.
     *
     * @param mixed  $condition
     * @param string $message
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function assertZero($condition, string $message = ''): void
    {
        self::assertEquals(0, $condition, $message);
    }

    /**
     * Asserts that a variable is instance of a Job class.
     *
     * @param mixed  $condition
     * @param string $message
     *
     * @throws ExpectationFailedException
     * @throws InvalidArgumentException
     */
    public function assertIsJob($condition, string $message = ''): void
    {
        self::assertInstanceOf(BaseJob::class, $condition, $message);
    }
}

<?php

namespace Qless;

use Qless\Exceptions\InvalidArgumentException;
use Qless\Exceptions\RedisConnectionException;

/**
 * Qless\Redis
 *
 * @package Qless
 */
final class Redis
{
    private const DEFAULT_PORT = 6379;
    private const DEFAULT_TIMEOUT = 0.0;
    private const VALID_SCHEMES = ['redis', 'tcp', 'unix'];

    /** @var string */
    private $host;

    /** @var ?int */
    private $port;

    /** @var ?int */
    private $database;

    /** @var ?string */
    private $user;

    /** @var ?string */
    private $password;

    /** @var array */
    private $options = [];

    /** @var float */
    private $timeout;

    /** @var \Redis */
    private $driver;

    /**
     * Redis constructor.
     *
     * @todo  Add persistence support.
     *
     * @param string   $dsn
     * @param int|null $database
     */
    public function __construct(string $dsn, ?int $database = null)
    {
        list($host, $port, $dsnDatabase, $user, $password, $options) = $this->parseDsn($dsn);

        $this->host = $host;
        $this->port = $port;

        if ($dsnDatabase !== null) {
            $database = $dsnDatabase;
        }

        $this->database = $database;
        $this->user = $user;
        $this->timeout = $options['timeout'];
        $this->password = $password;
        $this->options = $options ?: [];

        $this->driver = new \Redis();
    }

    public function __clone()
    {
        $this->driver = new \Redis();
    }

    /**
     * Connects to a Redis instance.
     *
     * @return void
     *
     * @throws RedisConnectionException
     */
    public function connect(): void
    {
        try {
            $this->driver->connect(
                $this->host,
                $this->port,
                $this->timeout
            );
        } catch (\RedisException $e) {
            throw new RedisConnectionException(rtrim($e->getMessage(), '.') . '.', $e->getCode(), $e);
        }

        if ($this->password !== null) {
            if ($this->driver->auth($this->password) == false) {
                throw new RedisConnectionException(
                    'Unable to authenticate the Redis instance using provided password.'
                );
            }
        }

        if ($this->database !== null) {
            if ($this->driver->select($this->database) == false) {
                throw new RedisConnectionException('Unable to select the Redis database.');
            }
        }
    }

    /**  @return \Redis */
    public function getDriver(): \Redis
    {
        return $this->driver;
    }


    /**
     * Parse a DSN string, which can have one of the following formats:
     *
     * - host:port
     * - redis://user:pass@host:port/db?option1=val1&option2=val2
     * - tcp://user:pass@host:port/db?option1=val1&option2=val2
     * - unix:///path/to/redis.sock
     *
     * Note: the 'user' part of the DSN is not used.
     *
     * @param  string $dsn
     * @return array
     *
     * @throws InvalidArgumentException
     */
    private function parseDsn(string $dsn)
    {
        if (empty($dsn)) {
            // We should not guess and magically connect to a host
            // that the user did not explicitly specified.
            // This is why we don not use "127.0.0.1:6379" here.
            throw new InvalidArgumentException('The Redis connection DSN is empty.');
        }

        if (substr($dsn, 0, 7) === 'unix://') {
            return [
                $dsn,
                null,
                null,
                null,
                null,
                null,
            ];
        }

        $parts = parse_url($dsn);

        // Check the URI scheme.
        if (isset($parts['scheme']) && in_array($parts['scheme'], self::VALID_SCHEMES) == false) {
            $valid = array_map(function (string $scheme) {
                return '"' . $scheme  . '://"';
            }, self::VALID_SCHEMES);

            throw new InvalidArgumentException(
                sprintf(
                    'Invalid Redis connection DSN. Supported schemes are: %s, got %s.',
                    implode(', ', $valid),
                    '"' . $parts['scheme'] . '://"'
                )
            );
        }

        // Allow simple 'hostname' format, which `parse_url` treats as a path, not host.
        if (isset($parts['host']) == false && isset($parts['path'])) {
            $parts['host'] = $parts['path'];
            unset($parts['path']);
        }

        // Extract the port number as an integer.
        $port = (int) $parts['port'] ?? self::DEFAULT_PORT;

        // Get the database from the 'path' part of the URI.
        $database = null;
        if (isset($parts['path'])) {
            // Strip non-digit chars from path.
            $database = (int) preg_replace('/[^0-9]/', '', $parts['path']);
        }

        // Extract any 'user' and 'pass' values.
        $user = $parts['user'] ?? null;
        $pass = $parts['pass'] ?? null;

        // Convert the query string into an associative array.
        $options = [];
        if (isset($parts['query'])) {
            // Parse the query string into an array.
            parse_str($parts['query'], $options);
        }

        $host = $parts['host'] ?? self::DEFAULT_HOST;

        $options['timeout'] = isset($options['timeout']) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;

        return [
            $host,
            $port,
            $database,
            $user,
            $pass,
            $options,
        ];
    }
}

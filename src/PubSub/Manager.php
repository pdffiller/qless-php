<?php

namespace Qless\PubSub;

use Predis\PubSub\Consumer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\Client;
use Qless\Exceptions\InvalidArgumentException;
use Predis\Client as Redis;
use Ramsey\Uuid\Exception\InvalidUuidStringException;
use Ramsey\Uuid\Uuid;

/**
 * Qless\PubSub\Manager
 *
 * Listen for qless-core notifications via PubSub
 *
 * @package Qless\PubSub
 */
final class Manager implements LoggerAwareInterface
{

    public const EVENT_CANCELED = 'canceled';
    public const EVENT_COMPLETED = 'completed';
    public const EVENT_FAILED = 'failed';
    public const EVENT_POPPED = 'popped';
    public const EVENT_STALLED = 'stalled';
    public const EVENT_PUT = 'put';
    public const EVENT_TRACK = 'track';
    public const EVENT_UNTRACK = 'untrack';

    /**
     * @var Redis
     */
    public $redis;

    /** @var Consumer|null */
    protected $pubSubConsumer;

    /** @var LoggerInterface */
    private $logger;

    /**
     * @var \Closure[][]
     */
    protected $callbacks = [
        self::EVENT_CANCELED => [],
        self::EVENT_COMPLETED => [],
        self::EVENT_FAILED => [],
        self::EVENT_POPPED => [],
        self::EVENT_STALLED => [],
        self::EVENT_PUT => [],
        self::EVENT_TRACK => [],
        self::EVENT_UNTRACK => []
    ];

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
        $this->logger = new NullLogger();
    }

    public function __destruct()
    {
        $this->stopListening();
    }

    /**
     * Convert an event name to a Qless PUBSUB channel name
     *
     * @param string $event
     *
     * @return string
     */
    protected static function channelName(string $event): string
    {
        return "ql:{$event}";
    }

    /**
     * Convert a Qless PUBSUB channel name to an event name
     *
     * @param string $channel
     *
     * @return string
     */
    protected static function eventName(string $channel): string
    {
        return \preg_replace('/^ql:/', '', $channel);
    }

    /**
     * Listen for Events of the given type.
     *
     * @param string $event The event type. Should be one of the PubSub\Manager::EVENT_* constants.
     * @param callable $callback The callback to invoke when the event occurs.
     *
     * @return $this
     */
    public function on(string $event, callable $callback): self
    {
        if (! \array_key_exists($event, $this->callbacks)) {
            throw new InvalidArgumentException(sprintf('event must be a known event type, got "%s"', $event));
        }

        $this->callbacks[$event][] = \Closure::fromCallable($callback);

        return $this;
    }

    /**
     * Listen for Events, and invoke registered callbacks.
     */
    public function listen(): void
    {
        $channels = \array_map([static::class, 'channelName'], \array_keys($this->callbacks));

        $this->redis->connect();

        $this->pubSubConsumer = $this->redis->pubSubLoop();
        $this->pubSubConsumer->subscribe(...$channels);
        /** @var \stdClass $message */
        foreach ($this->pubSubConsumer as $message) {
            $this->handleMessage($message);
        }
    }

    /**
     * Stop listening for events.
     */
    public function stopListening(): void
    {
        if ($this->pubSubConsumer) {
            $this->pubSubConsumer->stop(true);
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }


    /**
     * Handle a received message
     *
     * @param \stdClass $message
     */
    protected function handleMessage(\stdClass $message): void
    {
        if ($message->kind !== 'message' || empty($message->payload)) {
            return;
        }

        $jid = $message->payload;
        if (empty($jid)) {
            return;
        }

        try {
            Uuid::fromString($jid);
        } catch (InvalidUuidStringException $exception) {
            $this->logger->warning('Invalid JID received: ' . $jid, \compact('message'));
            return;
        }

        $event = static::eventName($message->channel);

        if (! array_key_exists($event, $this->callbacks)) {
            $this->logger->warning('Unknown event type received: ' . $event, \compact('message'));
            return;
        }

        foreach ($this->callbacks[$event] as $closure) {
            try {
                $closure($jid);
            } catch (\Throwable $exception) {
                $this->logger->error(
                    'Error while running callback: ' . $exception->getMessage(),
                    \compact('jid', 'event', 'exception')
                );
            }
        }
    }
}

<?php

namespace Qless\Subscribers;

use Predis\Client as Redis;
use Predis\PubSub\Consumer;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Qless\SystemFacade;

/**
 * Qless\Subscribers\WatchdogSubscriber
 *
 * A class used for subscribing to messages in a thread.
 *
 * @package Qless\Events
 */
class WatchdogSubscriber implements LoggerAwareInterface
{
    /** @var Redis */
    private $redis;

    /** @var array */
    private $channels = [];

    /** @var LoggerInterface */
    private $logger;

    /** @var mixed */
    private $defaultSocketTimeout;

    /** @var SystemFacade */
    private $system;

    private const LOCK_LOST = 'lock_lost';
    private const CANCELED = 'canceled';
    private const COMPLETED = 'completed';
    private const FAILED = 'failed';

    private const UNLIMITED = -1;

    private const WATCHDOG_EVENTS = [
        self::LOCK_LOST,
        self::CANCELED,
        self::COMPLETED,
        self::FAILED,
    ];

    /**
     * Subscriber constructor.
     *
     * @param Redis             $redis
     * @param array             $channels
     * @param SystemFacade|null $system
     */
    public function __construct(Redis $redis, array $channels, SystemFacade $system = null)
    {
        $this->redis = $redis;
        $this->channels = $channels;
        $this->logger = new NullLogger();
        $this->system = $system ?: new SystemFacade();

        $this->defaultSocketTimeout = ini_get('default_socket_timeout');
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
     * Watching events for a job.
     *
     * @param  string   $jid
     * @param  string   $worker
     * @param  int|null $pid
     * @return void
     */
    public function watchdog(string $jid, string $worker, ?int $pid = null): void
    {
        if (empty($this->channels) || $pid === null) {
            return;
        }

        ini_set('default_socket_timeout', self::UNLIMITED);

        /**
         * Initialize a new pubsub consumer.
         *
         * @var Consumer $pubsub|null
         */
        $pubsub = $this->redis->pubSubLoop();

        $callable = [$pubsub, 'subscribe'];
        if (!is_callable($callable)) {
            return;
        }

        call_user_func_array($callable, $this->channels);

        try {
            /** @var \stdClass $message */
            foreach ($pubsub as $message) {
                if ($message->kind !== 'message' || empty($message->payload)) {
                    continue;
                }

                $payload = json_decode($message->payload, true);
                if (empty($payload)) {
                    continue;
                }

                if (empty($payload['event']) || !is_array($payload)) {
                    continue;
                }

                if (!in_array($payload['event'], self::WATCHDOG_EVENTS, true) || empty($payload['jid'])) {
                    continue;
                }

                if ($payload['jid'] !== $jid) {
                    continue;
                }

                switch ($payload['event']) {
                    case self::LOCK_LOST:
                        if (!empty($payload['worker']) && $payload['worker'] === $worker) {
                            $this->logger->info(
                                "{type}: sending SIGKILL to child {$pid}; job {jid} handed out to another worker",
                                [
                                    'type' => 'watchdog:' . $worker,
                                    'jid' => $jid,
                                ]
                            );

                            $this->system->posixKill($pid, SIGKILL);
                            $pubsub->stop();
                        }
                        break;
                    case self::CANCELED:
                        if (!empty($payload['worker']) && $payload['worker'] === $worker) {
                            $this->logger->info(
                                "{type}: sending SIGKILL to child {$pid}; job {jid} canceled",
                                [
                                    'type' => 'watchdog:' . $worker,
                                    'jid' => $jid,
                                ]
                            );
                            $this->system->posixKill($pid, SIGKILL);
                            $pubsub->stop();
                        }
                        break;
                    case self::COMPLETED:
                    case self::FAILED:
                        $pubsub->stop();
                        break;
                }
            }
        } catch (\Throwable $exception) {
            $this->logger->critical(
                "Critical error",
                [
                    'type' => 'watchdog:' . $worker,
                    'jid' => $jid,
                    'exception' => $exception
                ]
            );
            $this->logger->info(
                "{type}: sending SIGKILL to child {$pid}; job {jid} canceled due to exception",
                [
                    'type' => 'watchdog:' . $worker,
                    'jid' => $jid,
                ]
            );
            $this->system->posixKill($pid, SIGKILL);
            $pubsub->stop();
        }

        // Always unset the pubsub consumer instance when you are done! The
        // class destructor will take care of cleanups and prevent protocol
        // desynchronizations between the client and the server.
        unset($pubsub);

        ini_set('default_socket_timeout', $this->defaultSocketTimeout);
    }
}

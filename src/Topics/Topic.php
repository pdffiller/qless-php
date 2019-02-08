<?php

namespace Qless\Topics;

use Qless\Client;
use Qless\Exceptions\BadMethodCallException;
use Qless\Jobs\BaseJob;
use Qless\Queues\Queue;

/**
 * Class Topic
 * @package Qless\Topics
 * @method array put(string $class, array $data, ?string $jid = null, ...$args)
 * @method BaseJob pop(?string $worker = null, int $numJobs = 0)
 */
class Topic
{
    /** @var string */
    private $name;

    /** @var Client */
    private $client;

    /** @var array */
    private $queues = [];

    /**
     * Topic constructor.
     * @param string $topic
     * @param Client $client
     */
    public function __construct(string $topic, Client $client)
    {
        $this->client = $client;
        $this->name = $topic;

        $this->queues = $this->client->getQueues()->fromSubscriptions($this->name);
    }

    /**
     * Multi access to queues
     * @param string $method
     * @param array $arguments
     * @return array
     */
    public function __call(string $method, array $arguments)
    {
        $response = [];
        foreach ($this->queues as $queueName) {
            $queue = new Queue($queueName, $this->client);
            $callable = [$queue, $method];
            if (!is_callable($callable)) {
                throw new BadMethodCallException();
            }
            $response[$queueName] = call_user_func_array($callable, $arguments);
        }

        return $response;
    }
}

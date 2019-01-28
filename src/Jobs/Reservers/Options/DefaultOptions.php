<?php

namespace Qless\Jobs\Reservers\Options;

class DefaultOptions implements ReserverOptionsInterface
{
    private $collection;
    private $spec;
    private $queues;
    private $worker;

    public function __construct(\ArrayAccess $collection)
    {
        $this->collection = $collection;
    }

    public function getCollection(): \ArrayAccess
    {
        return $this->collection;
    }

    public function getQueues(): ?array
    {
        return $this->queues ?? self::DEFAULT_QUEUES;
    }

    public function getSpec(): ?string
    {
        return $this->spec ?? self::DEFAULT_SPEC;
    }

    public function getWorker(): ?string
    {
        return $this->worker ?? self::DEFAULT_WORKER;
    }

    public function setWorker(string $worker): void
    {
        $this->worker = $worker;
    }

    public function setQueues(array $queues): void
    {
        $this->queues = $queues;
    }

    public function setSpec(string $spec): void
    {
        $this->spec = $spec;
    }
}

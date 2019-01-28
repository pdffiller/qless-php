<?php

namespace Qless\Jobs\Reservers\Options;

interface ReserverOptionsInterface
{
    public const DEFAULT_WORKER = null;
    public const DEFAULT_SPEC = null;
    public const DEFAULT_QUEUES = [];

    public function getCollection(): \ArrayAccess;
    public function getQueues(): ?array;
    public function getSpec(): ?string;
    public function getWorker(): ?string;
}

<?php

namespace Qless\Storage;

interface AutoPrioritiesStorageContract
{
    public const SECTIONS_NUMBER_DEFAULT = 100;

    public const TTL_DEFAULT = 10; // minutes

    public function setTtl(int $ttl = self::TTL_DEFAULT): void;

    public function getTtl(): int;

    public function setNumberSections(int $sections = self::SECTIONS_NUMBER_DEFAULT): void;

    public function getNumberSections(): int;

    public function saveQueuesData(array $queuesData): void;

    public function getQueuesData(): array;
}

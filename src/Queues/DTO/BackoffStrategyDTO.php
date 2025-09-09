<?php

namespace Qless\Queues\DTO;

class BackoffStrategyDTO
{
    /**
     * @var int
     */
    private $initialDelay;

    /**
     * @var int
     */
    private $factor;

    public function __construct(int $initialDelay, int $factor)
    {
        $this->initialDelay = $initialDelay;
        $this->factor = $factor;
    }

    /**
     * @return int
     */
    public function getFactor(): int
    {
        return $this->factor;
    }

    /**
     * @return int
     */
    public function getInitialDelay(): int
    {
        return $this->initialDelay;
    }

    public function toArray(): array
    {
        return [
            'factor' => $this->getFactor(),
            'initial_delay' => $this->getInitialDelay(),
        ];
    }
}

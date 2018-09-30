<?php

namespace Qless\Exceptions;

use Throwable;

/**
 * Qless\Exceptions\QlessException
 *
 * The default exception class for qless-core errors.
 *
 * @package Qless\Exceptions
 */
class QlessException extends RuntimeException
{
    /** @var string|null */
    protected $area;

    /** @var string|null */
    protected $jid;

    /**
     * QlessException constructor.
     *
     * @param string         $message
     * @param string|null    $area
     * @param string|null    $jid
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        ?string $area = null,
        ?string $jid = null,
        int $code = 0,
        Throwable $previous = null
    ) {
        $this->area = $area;
        $this->jid = $jid;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Gets current error area.
     *
     * @return string|null
     */
    public function getArea(): ?string
    {
        return $this->area;
    }

    /**
     * Gets current job id.
     *
     * @return string|null
     */
    public function getJid(): ?string
    {
        return $this->jid;
    }
}

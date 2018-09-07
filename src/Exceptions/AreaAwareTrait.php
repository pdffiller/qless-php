<?php

namespace Qless\Exceptions;

use Throwable;

/**
 * Qless\Exceptions\AreaAwareTrait
 *
 * @package Qless\Exceptions
 */
trait AreaAwareTrait
{
    /** @var string|null */
    protected $area;

    /**
     * QlessException constructor.
     *
     * @param string         $message
     * @param string|null    $area
     * @param int            $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message, string $area = null, $code = 0, Throwable $previous = null)
    {
        $this->area = $area;

        parent::__construct($message, $code, $previous);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Qless\Exceptions\ExceptionInterface::getArea()
     *
     * @return string|null
     */
    public function getArea()
    {
        return $this->area;
    }
}

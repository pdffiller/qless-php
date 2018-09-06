<?php

namespace Qless;

use Throwable;

/**
 * Qless\QlessException
 *
 * The base class for all qless exceptions.
 *
 * @package Qless
 */
class QlessException extends \Exception
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
    public function __construct($message, $area = null, $code = 0, Throwable $previous = null)
    {
        $this->area = $area;

        parent::__construct($message, $code, $previous);
    }

    /**
     * Factory method to create an exception class from an error message.
     *
     * @param  string $error
     * @return QlessException
     */
    public static function createExceptionFromError(string $error)
    {
        if (preg_match('/^ERR.*user_script:\d+:\s*(?<area>[\w.]+)\(\):\s*(?<message>.*)/', $error, $matches) > 0) {
            $area    = $matches['area'];
            $message = $matches['message'];
        } else {
            $area    = null;
            $message = $error;
        }

        switch (true) {
            case ($area === 'Requeue' && stripos($message, 'does not exist') !== false):
            case (stripos($message, 'Job does not exist') !== false):
                return new InvalidJobException($message, $area);

            case (stripos($message, 'Job given out to another worker') !== false):
                return new JobLostException($message, $area);

            case (stripos($message, 'Job not currently running') !== false):
            default:
                return new QlessException($message, $area);
        }
    }
}

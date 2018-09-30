<?php

namespace Qless\Exceptions;

/**
 * Qless\Exceptions\ExceptionFactory
 *
 * @package Qless\Exceptions
 */
class ExceptionFactory
{
    const ERROR_MESSAGE_RE = '/^ERR.*user_script:\d+:\s*(?<area>[\w.]+)\(\):\s*(?<message>.*)/';

    /**
     * Factory method to create an exception instance from an error message.
     *
     * @param  string $error
     * @return InvalidJobException|JobLostException|QlessException
     */
    public static function fromErrorMessage(string $error): QlessException
    {
        $area = null;
        $message = $error;

        if (preg_match(self::ERROR_MESSAGE_RE, $error, $matches) > 0) {
            $area    = $matches['area'];
            $message = $matches['message'];
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

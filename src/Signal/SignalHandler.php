<?php

namespace Qless\Signal;

use Psr\Log\LoggerInterface;
use Seld\Signal\SignalHandler as BaseHandler;

/**
 * Qless\Signal\SignalHandler
 *
 * @package Qless\Signal
 */
class SignalHandler extends BaseHandler
{
    /**
     * Clear all previously registered signal handlers.
     *
     * @param  string[]|int[]|null $signals
     * @return void
     */
    public function unregister(?array $signals = null): void
    {
        if (empty($signals)) {
            $signals = [SIGINT, SIGTERM];
        }

        foreach ($signals as $signal) {
            if (is_string($signal)) {
                // skip missing signals, for example OSX does not have all signals
                if (!defined($signal)) {
                    continue;
                }

                $signal = constant($signal);
            }

            pcntl_signal($signal, SIG_DFL);
        }
    }

    /**
     * Registers a signal handler.
     *
     * @param  array|null               $signals  And array of signal names (more portable) or constants.
     * @param  LoggerInterface|callable $callback A PSR-3 Logger or a callback($signal, $signalName)
     * @return SignalHandler
     */
    public static function register(?array $signals = null, $callback = null): SignalHandler
    {
        /** @var self $handler */
        $handler = self::create($signals, $callback);

        return $handler;
    }

    /**
     * Creates a human readable pcntl signal name by its code.
     *
     * @param  int $signal
     * @return string
     */
    public function name(int $signal): string
    {
        $signals = [
            'SIGHUP', 'SIGINT', 'SIGQUIT', 'SIGILL', 'SIGTRAP', 'SIGABRT', 'SIGIOT', 'SIGBUS',
            'SIGFPE', 'SIGKILL', 'SIGUSR1', 'SIGSEGV', 'SIGUSR2', 'SIGPIPE', 'SIGALRM', 'SIGTERM',
            'SIGSTKFLT', 'SIGCLD', 'SIGCHLD', 'SIGCONT', 'SIGSTOP', 'SIGTSTP', 'SIGTTIN', 'SIGTTOU',
            'SIGURG', 'SIGXCPU', 'SIGXFSZ', 'SIGVTALRM', 'SIGPROF', 'SIGWINCH', 'SIGPOLL', 'SIGIO',
            'SIGPWR', 'SIGSYS', 'SIGBABY',
        ];

        foreach ($signals as $name) {
            if (defined($name) && constant($name) === $signal) {
                return $name;
            }
        }

        return 'UNKNOWN';
    }
}

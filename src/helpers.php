<?php

namespace Qless;

/**
 * Creates a human readable pcntl signal name by its code.
 *
 * @param  int $signal
 * @return string
 */
function pcntl_sig_name(int $signal): string
{
    $all = get_defined_constants(true)['pcntl'];

    $filter = function (string $name) {
        return strpos($name, 'SIG') === 0 && strpos($name, 'SIG_') === false;
    };

    $filtered = array_filter(array_keys($all), $filter);
    $constants = array_flip(array_intersect_key($all, array_flip($filtered)));

    return isset($constants[$signal]) ? $constants[$signal] : 'UNKNOWN';
}

/**
 * Sets the process status.
 *
 * NOTE: Not supported on all systems.
 * NOTE:
 *
 * @param  string $value
 * @return void
 */
function procline(string $value): void
{
    if (false === @cli_set_process_title($value)) {
        if ('Darwin' === PHP_OS) {
            \trigger_error(
                'Running "cli_get_process_title" as an unprivileged user is not supported on macOS.',
                E_USER_WARNING
            );
        } else {
            $error = error_get_last();
            trigger_error($error['message'], E_USER_WARNING);
        }
    }
}

<?php

namespace Qless;

/**
 * Sets the process status.
 *
 * NOTE: Not supported on all systems.
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

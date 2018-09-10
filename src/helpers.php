<?php

namespace Qless;

/**
 * Creates a human readable pcntl signal name by its code.
 *
 * @param  int $signal
 * @return string
 */
function pcntl_sig_name(int $signal) : string
{
    $all = get_defined_constants(true)['pcntl'];

    $filtered = array_filter(array_keys($all), function ($k) {
        return strpos($k, 'SIG') === 0 && strpos($k, 'SIG_') === false;
    });

    $constants = array_flip(array_intersect_key($all, array_flip($filtered)));

    unset($all, $filtered);

    return isset($constants[$signal]) ? $constants[$signal] : 'UNKNOWN';
}

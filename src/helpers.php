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

    $filter = function (string $name) {
        return strpos($name, 'SIG') === 0 && strpos($name, 'SIG_') === false;
    };

    $filtered = array_filter(array_keys($all), $filter);
    $constants = array_flip(array_intersect_key($all, array_flip($filtered)));

    return isset($constants[$signal]) ? $constants[$signal] : 'UNKNOWN';
}

<?php

namespace React\Dns\Process;

/**
 * Utility functions.
 */
final class Util
{

    /**
     * Determine if the OS is Windows.
     *
     * @return bool
     */
    public static function isWindows()
    {
        return strcasecmp('win', strtolower(substr(PHP_OS, 0, 3))) === 0;
    }

}

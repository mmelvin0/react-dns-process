<?php

namespace React\Dns\Process;

final class Util
{

    /**
     * @return bool
     */
    public static function isWindows()
    {
        return strcasecmp('win', strtolower(substr(PHP_OS, 0, 3))) === 0;
    }

}

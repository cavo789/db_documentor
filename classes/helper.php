<?php

declare (strict_types = 1);

namespace Classes;

/**
 * A few helper function.
 */
class Helper
{
    public static function initDebug(bool $onOff)
    {
        if (true === $onOff) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            ini_set('html_errors', '1');
            ini_set('docref_root', 'http://www.php.net/');
            ini_set('error_prepend_string', "<div style='color:red; font-family:verdana; border:1px solid red; padding:5px;'>");
            ini_set('error_append_string', '</div>');
            error_reporting(E_ALL);
        } else {
            error_reporting(0);
        }
    }
}

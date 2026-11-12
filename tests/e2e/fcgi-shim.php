<?php

// Test-only stand-in for PHP-FPM's fastcgi_finish_request() on PHP's built-in server (serve.sh, FCGI_SHIM=1): sends the
// response so far, as PHP-FPM does, after which headers count as sent - the situation Baldrick's processing after the
// response must cope with.
if (!function_exists('fastcgi_finish_request')) {
    function fastcgi_finish_request()
    {
        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        flush();

        return true;
    }
}

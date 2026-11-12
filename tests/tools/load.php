<?php

// Usage: php load.php <joomla-dir> <class-map> <kunena-file>
// Boots Joomla 3's class loader (no database, no application) plus Kunena's autoloader, includes one Kunena file and
// reports PHP messages raised by Kunena code (ISSUE|...), exceptions (THROW|...) and fatal errors (FATAL|...).
$joomla = realpath($argv[1]);
$mapFile = $argv[2];
$target = realpath($argv[3]);
$repo   = dirname(__DIR__, 2);
error_reporting(E_ALL);
ini_set('display_errors', '0');
set_error_handler(function ($no, $str, $file, $line) use ($repo) {
    $rf = realpath($file) ?: $file;
    if (strpos($rf, $repo) === 0) {
        fwrite(STDOUT, "ISSUE|" . substr($rf, strlen($repo) + 1) . ":$line|$no|$str\n");
    }
    return true;
});
register_shutdown_function(function () use ($repo) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_PARSE])) {
        $rf = realpath($e['file']) ?: $e['file'];
        echo "FATAL|" . str_replace($repo . '/', '', $rf) . ":{$e['line']}|{$e['message']}\n";
    }
});
define('_JEXEC', 1);
$site = $joomla;
define('JPATH_BASE', $site);
require $site . '/includes/defines.php';
require JPATH_LIBRARIES . '/import.legacy.php';
require JPATH_LIBRARIES . '/cms.php';
define('KUNENA_PROFILER', 0);
define('KPATH_SITE', $repo . '/components/com_kunena');
define('KPATH_ADMIN', $repo . '/administrator/components/com_kunena');
define('KPATH_MEDIA', $repo . '/media/kunena');
define('KUNENA_COMPONENT_NAME', 'com_kunena');
define('KUNENA_NAME', 'kunena');
define('KURL_COMPONENT', 'index.php?option=com_kunena');
define('KURL_SITE', '/components/com_kunena/');
define('KURL_MEDIA', '/media/kunena/');
define('KPATH_COMPONENT_RELATIVE', 'components/com_kunena');
require $repo . '/libraries/kunena/bootstrap.php';
$map = include $mapFile;
spl_autoload_register(function ($c) use ($map) {
    $k = strtolower(ltrim($c, '\\'));
    if (isset($map[$k])) {
        require_once $map[$k][count($map[$k]) - 1];
    }
});
ob_start();
try {
    require_once $target;
} catch (\Throwable $e) {
    $rf = realpath($e->getFile()) ?: $e->getFile();
    echo "THROW|" . str_replace($repo . '/', '', $rf) . ":{$e->getLine()}|" . get_class($e) . ': ' . $e->getMessage() . "\n";
}
ob_end_clean();

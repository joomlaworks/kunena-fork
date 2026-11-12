<?php

// Test-site helper: runs posted PHP inside the Joomla site application with Kunena loaded (copied into test sites by run.php; never ship).
if (!is_file(__DIR__ . '/.kunena-test-site')) {
    http_response_code(404);
    exit;
}
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';
$app = JFactory::getApplication('site');
$app->initialise();
require_once JPATH_ADMINISTRATOR . '/components/com_kunena/api.php';
KunenaForum::setup();
if (!is_file(JPATH_BASE . '/.kunena-test-site') || !isset($_POST["c"]) || $_SERVER["REMOTE_ADDR"] !== "127.0.0.1") {
    die("no");
}
$code = $_POST["c"];
header('Content-Type: text/plain');
var_dump(eval($code));

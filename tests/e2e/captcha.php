<?php

// CAPTCHA and frontend credits scenarios, on an installed test site (run.php <db> install,wizard first).
//  - Kunena uses the CAPTCHA selected in Joomla's Global Configuration (IconCaptcha here, Joomla 3.x UTD's default)
//  - a bot posting without an answer is refused; fail-closed when no CAPTCHA plugin is configured
//  - right/wrong answers through Joomla's Captcha API, with the test-only "testcaptcha" plugin (answer: letmein)
//  - moderators are never challenged; no "Powered by Kunena" or credits page in the frontend
// Usage: php captcha.php <db>
require __DIR__ . '/lib.php';
$env   = parse_ini_string(preg_replace('/^(\w+)=/m', '$1=', shell_exec('bash -c ". ' . __DIR__ . '/env.sh; set | grep -E \'^(DBTEST|SITES|OUT|ADMIN_USER|ADMIN_PASS)=\'"')));
$db    = isset($argv[1]) ? $argv[1] : 'sqlite';
$ports = array('sqlite' => 8101, 'pg' => 8102, 'mariadb' => 8103, 'pgpdo' => 8104, 'mysql' => 8105);
$site  = $env['SITES'] . '/' . $db;
$base  = 'http://127.0.0.1:' . $ports[$db] . '/';
$out   = realpath($env['OUT']);
$fail  = 0;

function res($name, $ok, $note = '')
{
    global $fail;

    if (!$ok) {
        $fail++;
    }

    echo str_pad($name, 42) . ($ok ? 'PASS' : 'FAIL') . ($note ? "  $note" : '') . "\n";
}

function kexec($code)
{
    global $base, $out, $db, $site;
    copy(__DIR__ . '/kexec.php', "$site/kexec.php");

    return (new JClient($base, "$out/$db-kexec.jar"))->post('kexec.php', array('c' => $code));
}

// Joomla's command line; waits for the PHP built-in server's opcache to see the new configuration.php
function jcli($args)
{
    global $site;
    $r = shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php ' . $args . ' -n -q 2>&1');
    sleep(3);

    return $r;
}

function kurl($q)
{
    return 'index.php?option=com_kunena&' . $q;
}

function kconfig($captcha, $postLimit = 0)
{
    return kexec('$c = KunenaConfig::getInstance(); $c->captcha = ' . (int) $captcha . '; $c->captcha_post_limit = ' . (int) $postLimit
        . '; $c->floodprotection = 0; $c->pubwrite = 1; $c->save(); return "ok";');
}

/** A guest posts a new topic. Returns [created?, form html, response html]. */
function guestPost($label, $extra = array())
{
    global $base, $out, $db, $cid;
    @unlink("$out/$db-captcha-guest.jar");
    $g    = new JClient($base, "$out/$db-captcha-guest.jar");
    $form = $g->get(kurl("view=topic&layout=create&catid=$cid"));
    file_put_contents("$out/$db-captcha-$label-form.html", $form);
    $h      = JClient::hidden($form, 'id="postform"');
    $t      = JClient::token($form);
    $marker = "captcha-$label-" . uniqid();
    $g->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $cid, 'authorname' => 'Bot', 'email' => 'bot@example.com',
        'subject' => $marker, 'message' => "Spam $marker"), $extra, array($t => 1)));
    file_put_contents("$out/$db-captcha-$label-post.html", $g->last);
    $exists = kexec('$db = JFactory::getDbo(); $db->setQuery($db->getQuery(true)->select("COUNT(*)")->from("#__kunena_messages")->where("subject = " . $db->quote("' . $marker . '"))); return (int) $db->loadResult();');

    return array(strpos($exists, 'int(1)') !== false, $form, $g->last);
}

// Guests may post in every category; pick the last category
kexec('foreach (KunenaForumCategoryHelper::getCategories(false, false, "none") as $c) { $p = new Joomla\\Registry\\Registry($c->params); $p->set("access_post", array(1, 2, 6, 8, 9)); $p->set("access_reply", array(1, 2, 6, 8, 9)); $c->params = $p->toString(); $c->save(); } return "ok";');
$r = kexec('$ids = array(); foreach (KunenaForumCategoryHelper::getCategories(false, false, "none") as $c) { if (!$c->isSection()) $ids[] = $c->id; } return implode(",", $ids);');
preg_match('#"([\d,]+)"#', $r, $m);
$cats = isset($m[1]) ? explode(',', $m[1]) : array(2);
$cid  = (int) end($cats);

// 1. IconCaptcha selected in Global Configuration, Kunena requires a CAPTCHA from guests.
//    (Kunena 5.2 only validated reCAPTCHA-style keys and accepted everything else.)
jcli('extension:enable plg_captcha_iconcaptcha');
jcli('config:set captcha=iconcaptcha');
kconfig(1);
list($created, $form) = guestPost('iconcaptcha');
res('IconCaptcha widget rendered', stripos($form, 'iconcaptcha') !== false);
res('bot post without answer refused', !$created);

// 2. Kunena requires a CAPTCHA but none is selected in Global Configuration: fail closed.
jcli('config:set captcha=0');
list($created, $form, $resp) = guestPost('none');
res('not configured: post refused', !$created);
res('not configured: message shown', stripos($resp . $form, 'not properly configured') !== false);

// 3. Right and wrong answers through Joomla's Captcha API (test-only plugin, answer "letmein").
jcli('extension:install --path=' . escapeshellarg(__DIR__ . '/testcaptcha'));
jcli('extension:enable plg_captcha_testcaptcha');
jcli('config:set captcha=testcaptcha');
list($created, $form, $resp) = guestPost('wrong', array('kunena_captcha' => 'nope'));
res('testcaptcha field rendered as kunena_captcha', strpos($form, 'class="testcaptcha" name="kunena_captcha"') !== false);
res('wrong answer refused (plugin message)', !$created && strpos($resp, 'TESTCAPTCHA wrong answer') !== false);
list($created) = guestPost('right', array('kunena_captcha' => 'letmein'));
res('right answer accepted', $created);

// 4. Registered users below the post limit are challenged, moderators/administrators never.
kconfig(0, 1000);
$fe = new JClient($base, "$out/$db-captcha-admin.jar");
$fe->get('index.php');

if (strpos($fe->last, 'task=user.logout') === false) {
    $fe->siteLogin($env['ADMIN_USER'], $env['ADMIN_PASS']);
}

$form = $fe->get(kurl("view=topic&layout=create&catid=$cid"));
res('administrator not challenged', stripos($form, 'testcaptcha') === false && strpos($form, 'id="postform"') !== false);

// 5. Kunena CAPTCHA disabled: guests post normally.
kconfig(-1);
list($created) = guestPost('disabled');
res('CAPTCHA disabled: guest post accepted', $created);

jcli('config:set captcha=0');
jcli('extension:remove plg_captcha_testcaptcha');

// 6. No frontend credits
$v     = new JClient($base, "$out/$db-visitor.jar");
$pages = array(kurl('view=category&layout=list'), kurl("view=category&catid=$cid"), kurl('view=topics&mode=replies'), kurl('view=search'), kurl('view=credits'));
$bad   = array();

foreach ($pages as $p) {
    $html = $v->get($p);

    if (stripos($html, 'Powered by') !== false || stripos($html, 'kunena.org') !== false || stripos($html, 'id="credits"') !== false) {
        $bad[] = $p;
    }
}

res('no "Powered by Kunena" / credits page', !$bad, implode(' ', $bad));

@unlink("$site/kexec.php");
echo "== captcha ($db): " . ($fail ? "$fail failed" : 'all passed') . "\n";
exit($fail ? 1 : 0);

<?php

// Kunena end-to-end scenarios against a test site.
// Usage: php run.php <db> [step,step,...]   (db: sqlite|pg|pgpdo|mariadb|mysql; default steps: all)
require __DIR__ . '/lib.php';
$env = parse_ini_string(preg_replace('/^(\w+)=/m', '$1=', shell_exec('bash -c ". ' . __DIR__ . '/env.sh; set | grep -E \'^(DBTEST|SITES|OUT|ADMIN_USER|ADMIN_PASS)=\'"')));
$db   = isset($argv[1]) ? $argv[1] : 'sqlite';
$ports = array('sqlite' => 8101, 'pg' => 8102, 'mariadb' => 8103, 'pgpdo' => 8104, 'mysql' => 8105);
$all  = array('install', 'wizard', 'admin', 'config', 'site', 'post', 'guest', 'features', 'search', 'profile', 'recent', 'moderate', 'emptytrash', 'sampledata', 'extras', 'baldrick', 'adminpages', 'tools', 'uninstall');
$steps = isset($argv[2]) ? explode(',', $argv[2]) : $all;
$site = $env['SITES'] . '/' . $db;
$base = 'http://127.0.0.1:' . $ports[$db] . '/';
@mkdir($env['OUT'], 0777, true);
$out  = realpath($env['OUT']);
$results = array();
$dblog = "$site/administrator/logs/kunena-test-db-errors.php";
$dblogStart = is_file($dblog) ? filesize($dblog) : 0;
if (trim(shell_exec('find ' . escapeshellarg($site) . ' -type l | head -1'))) {
    echo "Refusing: $site contains symlinks (they could point into the Kunena repo)\n";
    exit(2);
}
$admin = new JClient($base, "$out/$db-admin.jar");
$state = is_file("$out/$db-state.json") ? json_decode(file_get_contents("$out/$db-state.json"), true) : array();
if (in_array('install', $steps)) {
    @unlink("$out/$db-admin.jar");
    @unlink("$out/$db-site.jar");
}

function save($name, $html)
{
    global $out, $db;
    $name = preg_replace("/[^A-Za-z0-9_.-]+/", "-", $name);
    file_put_contents("$out/$db-$name.html", $html);
}
function result($step, $ok, $note = '')
{
    global $results;
    $results[$step] = array($ok, $note);
    echo str_pad($step, 12) . ($ok ? 'PASS' : 'FAIL') . ($note ? "  $note" : '') . "\n";
}
function check($c, $step, $name, $needle = null)
{
    $html = $c->last;
    save($name, $html);
    $p = $c->problems();
    $ok = $c->code < 400 && !$p && ($needle === null || stripos($html, $needle) !== false);
    $note = "HTTP {$c->code}" . ($p ? ' | ' . substr(implode(' || ', $p), 0, 600) : '') . ($needle !== null && stripos($html, $needle) === false ? " | missing '$needle'" : '');
    return array($ok, $note);
}
function kurl($q)
{
    return 'index.php?option=com_kunena&' . $q;
}
/** Runs PHP in the test site (Kunena loaded) and decodes the JSON string it returns. */
function kexecJson($code)
{
    global $base, $out, $db, $site;
    copy(__DIR__ . '/kexec.php', "$site/kexec.php");
    $r = (new JClient($base, "$out/$db-kexec.jar"))->post('kexec.php', array('c' => $code));

    return preg_match('/^string\(\d+\) "(.*)"\s*$/s', $r, $m) ? json_decode($m[1], true) : array('error' => substr(strip_tags($r), 0, 800));
}
/**
 * The page's JSON-LD (number of blocks, nodes by @type, also inside @graph) and its OpenGraph / X tags
 */
function seoData($html)
{
    $data = array('blocks' => 0, 'nodes' => array(), 'meta' => array());
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    foreach ($m[1] as $json) {
        $data['blocks']++;
        $doc = json_decode($json, true);
        foreach (isset($doc['@graph']) ? $doc['@graph'] : array($doc) as $node) {
            if (isset($node['@type'])) {
                $data['nodes'][$node['@type']] = $node;
            }
        }
    }
    preg_match_all('#<meta (?:property|name)="((?:og|twitter|article):[^"]+)" content="([^"]*)"#', $html, $m, PREG_SET_ORDER);
    foreach ($m as $tag) {
        $data['meta'][$tag[1]] = html_entity_decode($tag[2], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    return $data;
}
function newTopic($c, $cid, $subject, $message)
{
    $html = $c->get(kurl("view=topic&layout=create&catid=$cid"));
    $h = JClient::hidden($html, 'id="postform"');
    $t = JClient::token($html);
    $c->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $cid, 'subject' => $subject, 'message' => $message, $t => 1)));
    return preg_match('#[?&](?:amp;)?id=(\d+)#', $c->lastUrl, $m) ? (int) $m[1] : 0;
}

if (!$admin->adminLogin($env['ADMIN_USER'], $env['ADMIN_PASS'])) {
    echo "admin login failed\n";
    save('login', $admin->last);
    exit(1);
}
// The same administrator, logged in on the site (frontend sessions are separate)
$fe = new JClient($base, "$out/$db-fe.jar");
if (in_array('install', $steps)) {
    @unlink("$out/$db-fe.jar");
}
$fe->get('index.php');
if (strpos($fe->last, 'task=user.logout') === false && !$fe->siteLogin($env['ADMIN_USER'], $env['ADMIN_PASS'])) {
    echo "site login failed\n";
    save('sitelogin', $fe->last);
}

foreach ($steps as $step) {
    try {
        switch ($step) {
            case 'install':
                $html = $admin->get('administrator/index.php?option=com_installer&view=install');
                $t = JClient::token($html);
                $admin->post('administrator/index.php?option=com_installer&view=install', array(
                    'install_package' => new CURLFile(getenv('KUNENA_ZIP') ?: $env['DBTEST'] . '/kunena-main.zip', 'application/zip', basename(getenv('KUNENA_ZIP') ?: 'kunena-main.zip')),
                    'installtype' => 'upload', 'task' => 'install.install', 'install_directory' => $site . '/tmp', 'install_url' => '', $t => 1));
                list($ok, $note) = check($admin, 'install', 'install');
                $ok = $ok && stripos($admin->lastUrl, 'view=install') !== false;
                result('install', $ok, $note . ' -> ' . $admin->lastUrl);
                break;
            case 'wizard':
                $html = $admin->get('administrator/' . kurl('view=install'));
                save('wizard-page', $html);
                $t = JClient::token($html);
                $ok = false;
                $note = '';
                $log = '';
                for ($i = 0; $i < 200; $i++) {
                    $r = $admin->post('administrator/' . kurl('view=install&task=run'), array($t => 1));
                    $j = json_decode($r, true);
                    if (!$j) {
                        $note = 'non-JSON: ' . substr(strip_tags($r), 0, 800);
                        break;
                    }
                    if (!empty($j['log'])) {
                        $log = $j['log'];
                    }
                    if (empty($j['success'])) {
                        $note = 'error: ' . strip_tags(isset($j['error']) ? $j['error'] : (isset($j['html']) ? $j['html'] : ''));
                        break;
                    }
                    if ($j['status'] === '100%') {
                        $ok = true;
                        break;
                    }
                }
                save('wizard-log', $log);
                if (preg_match_all('#<tr[^>]*>(.*?)</tr>#s', $log, $m)) {
                    foreach ($m[1] as $row) {
                        if (strpos($row, 'FAILED') !== false) {
                            $ok = false;
                            $note .= ' | ' . trim(preg_replace('/\s+/', ' ', strip_tags($row)));
                        }
                    }
                }
                result('wizard', $ok, $note);
                break;
            case 'admin':
                $admin->get('administrator/' . kurl('view=cpanel'));
                list($ok, $note) = check($admin, 'admin', 'admin-cpanel', 'kunena');
                result('admin', $ok, $note);
                break;
            case 'config':
                $html = $admin->get('administrator/' . kurl('view=config'));
                save('config-form', $html);
                // Collect every form field of the config form and post it back, changing the board title
                preg_match('#<form[^>]*id="adminForm".*?</form>#s', $html, $fm);
                $form = isset($fm[0]) ? $fm[0] : $html;
                $data = array();
                preg_match_all('#<input[^>]*>#', $form, $ins);
                foreach ($ins[0] as $tag) {
                    if (!preg_match('#name="([^"]+)"#', $tag, $n)) {
                        continue;
                    }
                    $type = preg_match('#type="([^"]+)"#', $tag, $tt) ? $tt[1] : 'text';
                    if (($type === 'radio' || $type === 'checkbox') && stripos($tag, 'checked') === false) {
                        continue;
                    }
                    $data[] = urlencode(html_entity_decode($n[1])) . '=' . urlencode(preg_match('#value="([^"]*)"#', $tag, $v) ? html_entity_decode($v[1]) : '');
                }
                preg_match_all('#<select[^>]*name="([^"]+)"[^>]*>(.*?)</select>#s', $form, $sels, PREG_SET_ORDER);
                foreach ($sels as $s) {
                    if (preg_match_all('#<option[^>]*value="([^"]*)"[^>]*selected#', $s[2], $o)) {
                        foreach ($o[1] as $v) {
                            $data[] = urlencode($s[1]) . '=' . urlencode(html_entity_decode($v));
                        }
                    }
                }
                preg_match_all('#<textarea[^>]*name="([^"]+)"[^>]*>(.*?)</textarea>#s', $form, $tas, PREG_SET_ORDER);
                foreach ($tas as $s) {
                    $data[] = urlencode($s[1]) . '=' . urlencode(html_entity_decode($s[2]));
                }
                $body = implode('&', $data);
                $body = preg_replace('/(^|&)task=[^&]*/', '', $body);
                $body = preg_replace('/(^|&)cfg_board_title=[^&]*/', '$1cfg_board_title=' . urlencode("Forum $db"), $body);
                $body = preg_replace('/(^|&)cfg_pubwrite=[^&]*/', '$1cfg_pubwrite=1', $body);
                $body = preg_replace('/(^|&)cfg_captcha=[^&]*/', '$1cfg_captcha=0', $body);
                // Settings which can't be empty: emptied, they get their defaults back (and the administrator is told)
                foreach (array('cfg_filetypes', 'cfg_imagemimetypes', 'cfg_threads_per_page') as $emptied) {
                    $body = preg_replace('/(^|&)' . $emptied . '=[^&]*/', '$1' . $emptied . '=', $body);
                }
                $body .= '&task=save';
                $admin->post('administrator/index.php?option=com_kunena&view=config', $body);
                list($ok, $note) = check($admin, 'config', 'config-save');
                $restoredNotice = strpos($admin->last, 'were set back to their default values') !== false;
                $admin->get('administrator/' . kurl('view=config'));
                $ok = $ok && strpos($admin->last, "Forum $db") !== false;
                result('config', $ok, $note);
                $cfgNow = kexecJson('$c = KunenaConfig::getInstance(); return json_encode(array($c->filetypes, $c->imagemimetypes, (int) $c->threads_per_page));');
                result('config-empty-defaults', $restoredNotice && is_array($cfgNow) && strpos($cfgNow[0], 'docx') !== false && strpos($cfgNow[1], 'image/webp') !== false && $cfgNow[2] === 20, json_encode($cfgNow));
                break;
            case 'site':
                $site_c = new JClient($base, "$out/$db-site.jar");
                $site_c->get(kurl('view=category&layout=list'));
                list($ok, $note) = check($site_c, 'site', 'site-index', 'kunena');
                preg_match_all('#view=category&(?:amp;)?catid=(\d+)#', $site_c->last, $m);
                $cats = array_values(array_unique($m[1]));
                if (!$cats) {
                    preg_match_all('#catid=(\d+)#', $site_c->last, $m);
                    $cats = array_values(array_unique($m[1]));
                }
                $state['cats'] = $cats;
                result('site-index', $ok, $note . ' cats=' . implode(',', $cats));
                // Blackadder is the default template: its frame, its two files only, no page generation time
                // (KUNENA_EXPECT_TEMPLATE=crypsis: a site upgraded from Kunena < 5.3, which keeps Crypsis)
                $html   = $site_c->last;
                $expect = getenv('KUNENA_EXPECT_TEMPLATE') ?: 'blackadder';
                if ($expect !== 'blackadder') {
                    result("template-$expect", strpos($html, "/template/$expect/") !== false && strpos($html, 'blackadder.css') === false);
                } else {
                    $assets = preg_match_all('#(?:href|src)="[^"]*/(?:components/com_kunena/template|media/kunena)/[^"?]+#', $html, $am) ? $am[0] : array();
                    $other  = array_filter($assets, function ($a) {
                        return !preg_match('#blackadder/assets/(css/blackadder\.css|js/blackadder\.js|images/)|media/kunena/(avatars|images|ranks|emoticons|topic_icons|category_icons)/#', $a);
                    });
                    result('blackadder-default', strpos($html, 'class="kb layout') !== false && strpos($html, 'blackadder.css') !== false && !$other
                        && stripos($html, 'Time to create page') === false, $other ? 'other assets: ' . implode(' ', array_slice($other, 0, 3)) : '');
                }
                foreach (array_slice($cats, 0, 3) as $cid) {
                    $site_c->get(kurl("view=category&catid=$cid"));
                    list($ok, $note) = check($site_c, 'site', "site-cat-$cid");
                    result("category-$cid", $ok, $note);
                }
                break;
            case 'post':
                $cats = isset($state['cats']) ? $state['cats'] : array(2);
                $cid = end($cats);
                $html = $fe->get(kurl("view=topic&layout=create&catid=$cid"));
                save('create-form', $html);
                $h = JClient::hidden($html, 'id="postform"');
                $t = JClient::token($html);
                $subject = "Test topic on $db " . date('His');
                $fe->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $cid, 'subject' => $subject, 'message' => "Hello [b]world[/b] from $db", $t => 1)));
                list($ok, $note) = check($fe, 'post', 'post-topic', $subject);
                if (preg_match('#[?&](?:amp;)?id=(\d+)#', $fe->lastUrl, $m) || preg_match('#view=topic&(?:amp;)?catid=\d+&(?:amp;)?id=(\d+)#', $fe->last, $m)) {
                    $state['topic'] = (int) $m[1];
                }
                $state['subject'] = $subject;
                result('post-topic', $ok, $note . ' topic=' . (isset($state['topic']) ? $state['topic'] : '?') . ' url=' . $fe->lastUrl);
                if (!empty($state['topic'])) {
                    $tid = $state['topic'];
                    $html = $fe->get(kurl("view=topic&layout=reply&catid=$cid&id=$tid"));
                    save('reply-form', $html);
                    $h = JClient::hidden($html, 'id="postform"');
                    $t = JClient::token($html);
                    $fe->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $cid, 'subject' => 'Re: ' . $subject, 'message' => "A reply on $db " . date('His'), $t => 1)));
                    list($ok, $note) = check($fe, 'post', 'post-reply', "A reply on $db");
                    result('post-reply', $ok, $note);
                    $fe->get(kurl("view=topic&catid=$cid&id=$tid"));
                    list($ok, $note) = check($fe, 'post', 'topic-view', "A reply on $db");
                    result('topic-view', $ok, $note);

                    // Structured data and sharing tags, as a search engine (a guest) sees them: one JSON-LD graph
                    // (Kunena's node joins Joomla 3.x UTD's) with the thread and the reply, OpenGraph and X tags
                    $g = new JClient($base, "$out/$db-seo.jar");
                    @unlink("$out/$db-seo.jar");
                    $page  = $g->get(kurl("view=topic&catid=$cid&id=$tid"));
                    $seo   = seoData($page);
                    $post  = isset($seo['nodes']['DiscussionForumPosting']) ? $seo['nodes']['DiscussionForumPosting'] : array();
                    $reply = isset($post['comment'][0]) ? $post['comment'][0] : array();
                    // (a moderator's "Re: ..." reply renames the thread: Kunena's allow_change_subject)
                    $ok    = $seo['blocks'] === 1 && $post && strpos($post['headline'], $subject) !== false && strpos($post['text'], 'Hello world') === 0
                        && !empty($post['author']['name']) && !empty($post['datePublished']) && strpos($post['url'], 'http') === 0
                        && $reply && strpos($reply['text'], "A reply on $db") === 0 && strpos($reply['url'], '#') !== false
                        && $post['interactionStatistic'][0]['interactionType'] === 'https://schema.org/CommentAction'
                        && isset($seo['meta']['og:title']) && $seo['meta']['og:title'] === $post['headline'] && $seo['meta']['og:type'] === 'article'
                        && isset($seo['meta']['twitter:card']) && strpos($seo['meta']['og:description'], 'Hello world') === 0;
                    result('seo-topic', $ok, json_encode(array('blocks' => $seo['blocks'], 'types' => array_keys($seo['nodes']), 'meta' => $seo['meta'])));

                    // A link without a menu item (Itemid) is redirected to one, keeping its page: it used to land on page 1
                    $g->get("index.php?option=com_kunena&view=topic&catid=$cid&id=$tid&limit=1&limitstart=1");
                    result('itemid-redirect-page', strpos($g->lastUrl, 'Itemid=') !== false && strpos($g->lastUrl, 'limitstart=1') !== false && strpos($g->lastUrl, 'limit=1') !== false, $g->lastUrl);
                }
                break;
            case 'guest':
                // Let guests (Public/Guest groups) post in every category, through Kunena's own API
                copy(__DIR__ . '/kexec.php', "$site/kexec.php");
                $r = (new JClient($base, "$out/$db-kexec.jar"))->post('kexec.php', array('c' => 'foreach (KunenaForumCategoryHelper::getCategories(false, false, "none") as $c) { $p = new Joomla\\Registry\\Registry($c->params); $p->set("access_post", array(1, 2, 6, 8, 9)); $p->set("access_reply", array(1, 2, 6, 8, 9)); $c->params = $p->toString(); $c->save(); } return "ok";'));
                if (strpos($r, '"ok"') === false) {
                    echo "kexec: " . substr(strip_tags($r), 0, 500) . "\n";
                }
                $g = new JClient($base, "$out/$db-guest.jar");
                @unlink("$out/$db-guest.jar");
                $cats = isset($state['cats']) ? $state['cats'] : array(2);
                $posted = false;
                $note = 'no category allows guest posting';
                $stamp = date('His');
                foreach (array_reverse($cats) as $cid) {
                    $html = $g->get(kurl("view=topic&layout=create&catid=$cid"));
                    if (strpos($html, 'id="postform"') === false) {
                        continue;
                    }
                    save('guest-form', $html);
                    $h = JClient::hidden($html, 'id="postform"');
                    $t = JClient::token($html);
                    $subject = "Guest topic on $db";
                    $g->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $cid, 'authorname' => 'Guesty', 'email' => 'guest@example.com', 'subject' => $subject, 'message' => "Guest message on $db $stamp", $t => 1)));
                    list($posted, $note) = check($g, 'guest', 'guest-post', "Guest message on $db $stamp");
                    $note .= " cat=$cid";
                    break;
                }
                result('guest-post', $posted, $note);
                break;
            case 'features':
                // Resolved threads, automatic locking, WebP/AVIF images, the configuration page's new settings
                copy(__DIR__ . '/kexec.php', "$site/kexec.php");
                $cats = isset($state['cats']) ? $state['cats'] : array(2);
                $cid  = end($cats);
                $tid  = isset($state['topic']) ? (int) $state['topic'] : 0;
                $replyAs = function ($c, $resolved, $text) use ($cid, $tid) {
                    $html = $c->get(kurl("view=topic&layout=reply&catid=$cid&id=$tid"));
                    $box  = strpos($html, 'name="resolved"') !== false;
                    $h    = JClient::hidden($html, 'id="postform"');
                    $t    = JClient::token($html);
                    $c->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $cid, 'id' => $tid, 'subject' => 'Re: x', 'message' => $text, 'resolved' => $resolved, $t => 1)));

                    return $box;
                };
                $labelOf = function () use ($tid) {
                    return kexecJson('return json_encode((int) JFactory::getDbo()->setQuery("SELECT label_id FROM #__kunena_topics WHERE id = ' . $tid . '")->loadResult());');
                };
                // The starter/moderator gets the checkbox, a guest doesn't; a reply marks the thread resolved, another reopens it
                $g = new JClient($base, "$out/$db-features.jar");
                @unlink("$out/$db-features.jar");
                $guestBox = strpos($g->get(kurl("view=topic&layout=reply&catid=$cid&id=$tid")), 'name="resolved"') !== false;
                $box      = $replyAs($fe, 1, "Resolving reply on $db");
                $label1   = $labelOf();
                $page     = $g->get(kurl("view=topic&catid=$cid&id=$tid"));
                $list     = $g->get(kurl("view=category&catid=$cid"));
                result('resolved-mark', $tid && $box && !$guestBox && $label1 === 8 && strpos($page, 'kb-chip--resolved') !== false && strpos($list, 'kb-chip--resolved') !== false, json_encode(array($box, $guestBox, $label1)));
                $replyAs($fe, 0, "Reopening reply on $db");
                $label2 = $labelOf();
                result('resolved-reopen', $label2 === 0 && strpos($g->get(kurl("view=topic&catid=$cid&id=$tid")), 'kb-chip--resolved') === false, json_encode($label2));

                // Automatic locking: Kunena's setting, the category's own (0: never), moderators still reply
                $lock = kexecJson('KunenaForum::setup(); $db = JFactory::getDbo(); $cfg = KunenaConfig::getInstance(); $guest = KunenaUserHelper::get(0); $mod = KunenaUserHelper::get(JFactory::getDbo()->setQuery("SELECT id FROM #__users WHERE username = ' . "'" . $env['ADMIN_USER'] . "'" . '")->loadResult());
                    $db->setQuery("UPDATE #__kunena_topics SET last_post_time = " . (time() - 3 * 86400) . " WHERE id = ' . $tid . '")->execute();
                    $r = array();
                    $t = KunenaForumTopicHelper::get(' . $tid . ', true); $r["before"] = array($t->isAutoLocked(), $t->isAuthorised("reply", $guest));
                    $cfg->set("autolock_days", 2); $cfg->save();
                    $t = KunenaForumTopicHelper::get(' . $tid . ', true); $r["global"] = array($t->isAutoLocked(), $t->isLocked(), $t->isAuthorised("reply", $guest), $t->isAuthorised("reply", $mod), $t->getAutoLockDays());
                    $c = $t->getCategory(); $p = new Joomla\\Registry\\Registry($c->params); $p->set("autolock_days", "0"); $c->params = $p->toString(); $c->save();
                    $t = KunenaForumTopicHelper::get(' . $tid . ', true); $c = $t->getCategory(); $c->params = $p->toString(); $r["category"] = array($t->isAutoLocked(), $t->getAutoLockDays());
                    $p->set("autolock_days", ""); $c->params = $p->toString(); $c->save();
                    return json_encode($r);');
                $locked = $g->get(kurl("view=topic&catid=$cid&id=$tid"));
                kexecJson('$cfg = KunenaConfig::getInstance(); $cfg->set("autolock_days", 0); $cfg->save(); KunenaForumTopicHelper::recount(' . $tid . '); return json_encode("ok");');
                $ok = isset($lock['global']) && $lock['before'] === array(false, true) && $lock['global'] === array(true, true, false, true, 2) && $lock['category'] === array(false, 0)
                    && strpos($locked, 'no reply for 2 days') !== false;
                result('autolock', $ok, json_encode($lock));

                // WebP (and AVIF where PHP's GD has it): uploaded images are read, resized and written like the others
                $img = kexecJson('$r = array("webp" => KunenaImage::canProcess("webp"), "avif" => KunenaImage::canProcess("avif"));
                    foreach (array("webp" => IMAGETYPE_WEBP, "avif" => defined("IMAGETYPE_AVIF") ? IMAGETYPE_AVIF : 0) as $ext => $type) {
                        if (!$r[$ext]) { continue; }
                        $src = JPATH_SITE . "/tmp/kunena-test.$ext"; $dst = JPATH_SITE . "/tmp/kunena-test-small.$ext";
                        $h = imagecreatetruecolor(300, 200); imagealphablending($h, false); imagesavealpha($h, true); imagefill($h, 0, 0, imagecolorallocatealpha($h, 0, 128, 255, 60));
                        $ext === "webp" ? imagewebp($h, $src, 90) : imageavif($h, $src, 90);
                        $i = new KunenaImage($src); $s = $i->resize(100, 100, false); $s->toFile($dst, $type, array("quality" => 80));
                        $p = KunenaImage::getImageFileProperties($dst); $r[$ext . "_out"] = array($p->width, $p->height, $p->mime);
                        @unlink($src); @unlink($dst);
                    }
                    return json_encode($r);');
                $ok = !empty($img['webp']) && $img['webp_out'] === array(100, 67, 'image/webp') && (empty($img['avif']) || $img['avif_out'] === array(100, 67, 'image/avif'));
                result('images-webp-avif', $ok, json_encode($img));

                // A real upload through Kunena's upload task (as Blackadder's posting form sends it), with "Check MIME types" on
                $made = kexecJson('$r = array(); foreach (array("webp", "avif") as $ext) { if (!KunenaImage::canProcess($ext)) { continue; } $f = JPATH_SITE . "/tmp/kunena-upload-test.$ext"; $h = imagecreatetruecolor(120, 80); imagefill($h, 0, 0, imagecolorallocate($h, 30, 120, 200)); $ext === "webp" ? imagewebp($h, $f, 80) : imageavif($h, $f, 80); $r[$ext] = $f; } return json_encode(array("files" => $r, "check" => (int) KunenaConfig::getInstance()->checkmimetypes));');
                $form = $fe->get(kurl("view=topic&layout=reply&catid=$cid&id=$tid"));
                $uploads = array();
                if (preg_match('#data-kb-upload-url="([^"]+)"#', $form, $um) && !empty($made['files'])) {
                    foreach ($made['files'] as $ext => $file) {
                        $fe->post(html_entity_decode($um[1]), array('file' => new CURLFile($file, "image/$ext", "photo.$ext"), 'catid' => $cid, 'filename' => "photo.$ext", 'size' => filesize($file), 'mime' => "image/$ext"));
                        $json = json_decode($fe->last, true);
                        $uploads[$ext] = !empty($json['success']) && !empty($json['data']['id']) ? (int) $json['data']['id'] : substr(strip_tags($fe->last), 0, 200);
                    }
                }
                $stored = array();
                foreach ($uploads as $ext => $id) {
                    if (is_int($id)) {
                        // Checked, then deleted (later scenarios count the attachments)
                        $stored[$ext] = kexecJson('$a = KunenaAttachmentHelper::get(' . $id . '); $r = array($a->filetype, is_file(JPATH_SITE . "/" . $a->folder . "/" . $a->filename)); $a->delete(); return json_encode($r);');
                    }
                }
                result('upload-webp-avif', !empty($made['check']) && isset($stored['webp']) && $stored['webp'] === array('image/webp', true) && (!isset($made['files']['avif']) || (isset($stored['avif']) && $stored['avif'] === array('image/avif', true))), json_encode(array($uploads, $stored)));

                // Configuration: SEO and Development tabs, automatic locking, resolved threads, units beside their fields
                $admin->get('administrator/' . kurl('view=config'));
                $cfgPage = $admin->last;
                result('config-new-settings', strpos($cfgPage, 'id="tab_seo"') !== false && strpos($cfgPage, 'id="tab_development"') !== false && strpos($cfgPage, 'name="cfg_autolock_days"') !== false
                    && strpos($cfgPage, 'name="cfg_resolve_threads"') !== false && strpos($cfgPage, 'name="cfg_dev_disable_email"') !== false && substr_count($cfgPage, 'kunena-unit-field') >= 8
                    && preg_match('/name="cfg_avatartypes"[^>]*value="[^"]*webp,avif"/', $cfgPage), 'HTTP ' . $admin->code);

                // Cache busting: Kunena's stylesheets and scripts carry the version (site and administrator); the version line is centred
                $version = kexecJson('return json_encode(KunenaForum::version());');
                $sitePage = $g->get(kurl("view=category&catid=$cid"));
                $ok = is_string($version) && preg_match('#blackadder\.css\?[^"]*v=' . preg_quote($version, '#') . '#', $sitePage) && preg_match('#blackadder\.js\?[^"]*v=' . preg_quote($version, '#') . '#', $sitePage)
                    && preg_match('#media/css/styles\.css\?v=' . preg_quote($version, '#') . '#', $cfgPage) && strpos($cfgPage, 'class="kunena-version small"') !== false
                    && !preg_match('#(com_kunena|media/kunena)/[^"?]+\.(css|js)"#', $sitePage . $cfgPage);
                result('assets-versioned', $ok, json_encode($version));
                break;
            case 'search':
                $site_c = new JClient($base, "$out/$db-site.jar");
                $site_c->get(kurl('view=search&task=results&query=' . urlencode('world') . '&titleonly=0'));
                list($ok, $note) = check($site_c, 'search', 'search', isset($state['subject']) ? $state['subject'] : null);
                result('search', $ok, $note);
                $fe->get(kurl('view=search&task=results&query=' . urlencode('reply') . '&searchuser=admin&childforums=1'));
                list($ok, $note) = check($fe, 'search', 'search-admin', 'A reply on');
                result('search-user', $ok, $note);
                break;
            case 'profile':
                $fe->get(kurl('view=user'));
                list($ok, $note) = check($fe, 'profile', 'profile-own', 'admin');
                result('profile', $ok, $note);
                $fe->get(kurl('view=user&layout=list'));
                list($ok, $note) = check($fe, 'profile', 'userlist');
                result('userlist', $ok, $note);
                $html = $fe->get(kurl('view=user&layout=edit'));
                list($ok, $note) = check($fe, 'profile', 'profile-edit');
                result('profile-edit', $ok, $note);
                break;
            case 'recent':
                $site_c = new JClient($base, "$out/$db-site.jar");
                foreach (array('recent' => 'view=topics', 'unread' => 'view=topics&mode=unread', 'posts' => 'view=topics&layout=posts&mode=recent', 'mytopics' => 'view=topics&layout=user&mode=default', 'myposts' => 'view=topics&layout=posts&mode=latest&userid=0', 'stats' => 'view=statistics', 'who' => 'view=credits') as $k => $q) {
                    $c = in_array($k, array('mytopics', 'myposts', 'unread')) ? $fe : $site_c;
                    $c->get(kurl($q));
                    list($ok, $note) = check($c, 'recent', "recent-$k");
                    result($k, $ok, $note);
                }
                break;
            case 'emptytrash':
                // Setup: a thread trashed completely and a live thread with one trashed reply, both with an attachment file
                $setup = kexecJson(
                    <<<'PHP'
$db = JFactory::getDbo();
$db->setQuery("SELECT id FROM #__users WHERE username = 'admin'");
$uid = (int) $db->loadResult(); $me = KunenaUserHelper::get($uid);
$cat = null;
foreach (KunenaForumCategoryHelper::getCategories(false, false, 'none') as $c) { if (!$c->isSection()) { $cat = $c; } }
$attach = function ($message, $name) use ($me) {
    KunenaForumMessageHelper::get($message->id, true); // refresh the helper's cache of the new post
    $folder = 'media/kunena/attachments/' . (int) $me->userid;
    @mkdir(JPATH_ROOT . '/' . $folder, 0755, true);
    file_put_contents(JPATH_ROOT . "/$folder/$name", 'trash test ' . $name);
    $a = new KunenaAttachment();
    $a->mesid = $message->id; $a->userid = $me->userid; $a->folder = $folder; $a->filename = $name; $a->filename_real = $name;
    $a->filetype = 'text/plain'; $a->size = filesize(JPATH_ROOT . "/$folder/$name"); $a->hash = md5_file(JPATH_ROOT . "/$folder/$name"); $a->protected = 0;
    if (!$a->save()) { throw new Exception('attachment: ' . $a->getError()); }
    return "$folder/$name";
};
$stamp = uniqid();
$reply = function ($parent, $text) use ($me) {
    list(, $r) = $parent->newReply(array('message' => $text));
    $r->userid = $me->userid; $r->name = $me->getName('');
    if (!$r->save()) { throw new Exception('reply: ' . $r->getError()); }
    return $r;
};
list($t1, $m1) = $cat->newTopic(array('subject' => "Trash me $stamp", 'message' => "Whole thread to trash $stamp"), $me); if (!$m1->save()) throw new Exception('t1 ' . $m1->getError());
$t1 = KunenaForumTopicHelper::get($m1->thread, true);
$r1 = $reply($m1, "Reply in trashed thread $stamp");
$f1 = $attach($r1, "trash-$stamp-a.txt");
list($t2, $m2) = $cat->newTopic(array('subject' => "Keep me $stamp", 'message' => "Live thread $stamp"), $me); if (!$m2->save()) throw new Exception('t2 ' . $m2->getError());
$r2 = $reply($m2, "Reply to trash $stamp");
$r3 = $reply($m2, "Reply to keep $stamp");
$f2 = $attach($r2, "trash-$stamp-b.txt");
return json_encode(array('t1' => (int) $m1->thread, 't2' => (int) $m2->thread, 'r1' => (int) $r1->id, 'r2' => (int) $r2->id, 'r3' => (int) $r3->id,
    'files' => array($f1, $f2), 'cat' => (int) $cat->id));
PHP
                );
                // Trash in a request of its own, like on a real site (Kunena's object caches are per request)
                if (!empty($setup['t1'])) {
                    $trashed = kexecJson('if (!KunenaForumTopicHelper::get(' . (int) $setup['t1'] . ')->publish(KunenaForum::DELETED)) throw new Exception("trash t1");
                        if (!KunenaForumMessageHelper::get(' . (int) $setup['r2'] . ')->publish(KunenaForum::DELETED)) throw new Exception("trash r2");
                        return json_encode(KunenaForumTrash::count());');
                    $setup['counts'] = $trashed;
                }
                if (empty($setup['t1'])) {
                    result('emptytrash', false, 'setup failed: ' . json_encode($setup));
                    break;
                }
                $files = $setup['files'];
                $filesBefore = count(array_filter($files, function ($f) use ($site) {
                    return is_file("$site/$f");
                }));
                // The Tools page lists the counts and gives the AJAX URL
                $html = $admin->get('administrator/' . kurl('view=tools&layout=emptytrash'));
                save('emptytrash-page', $html);
                $ok = $admin->code == 200 && strpos($html, 'kunena-emptytrash-start') !== false && strpos($html, 'Warning:') === false;
                result('emptytrash-page', $ok && $setup['counts']['topics'] >= 1 && $setup['counts']['messages'] >= 1, "HTTP {$admin->code} counts=" . json_encode($setup['counts']));
                $url = preg_match('#data-url="([^"]+)"#', $html, $m) ? html_entity_decode($m[1]) : '';
                $done = false;
                $last = null;
                for ($i = 0, $stepName = 'init'; $url && $i < 200; $i++, $stepName = 'run') {
                    $last = json_decode($admin->post('administrator/' . $url, array('step' => $stepName)), true);
                    if (!$last || empty($last['success']) || !empty($last['done'])) {
                        $done = $last && !empty($last['done']);
                        break;
                    }
                }
                $after = kexecJson('$db = JFactory::getDbo(); $q = function ($sql) use ($db) { $db->setQuery($sql); return (int) $db->loadResult(); };
                    $t2 = KunenaForumTopicHelper::get(' . (int) $setup['t2'] . ', true);
                    $c = KunenaForumCategoryHelper::get(' . (int) $setup['cat'] . ');
                    return json_encode(array("trash" => KunenaForumTrash::count(),
                        "t1" => $q("SELECT COUNT(*) FROM #__kunena_topics WHERE id=' . (int) $setup['t1'] . '"),
                        "t1msgs" => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE thread=' . (int) $setup['t1'] . '"),
                        "r2" => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE id=' . (int) $setup['r2'] . '"),
                        "r3" => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE id=' . (int) $setup['r3'] . ' AND hold=0"),
                        "texts" => $q("SELECT COUNT(*) FROM #__kunena_messages_text WHERE mesid IN (' . (int) $setup['r1'] . ',' . (int) $setup['r2'] . ')"),
                        "attachments" => $q("SELECT COUNT(*) FROM #__kunena_attachments WHERE mesid IN (' . (int) $setup['r1'] . ',' . (int) $setup['r2'] . ')"),
                        "t2posts" => (int) $t2->posts, "t2real" => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE thread=' . (int) $setup['t2'] . ' AND hold=0"),
                        "catposts" => (int) $c->numPosts, "catreal" => $q("SELECT COUNT(*) FROM #__kunena_messages AS m INNER JOIN #__kunena_topics AS t ON t.id=m.thread WHERE m.catid=' . (int) $setup['cat'] . ' AND m.hold=0 AND t.hold=0")));');
                $filesAfter = count(array_filter($files, function ($f) use ($site) {
                    return is_file("$site/$f");
                }));
                $ok = $done && $after && empty($after['error']) && $after['trash']['topics'] == 0 && $after['trash']['messages'] == 0
                    && !$after['t1'] && !$after['t1msgs'] && !$after['r2'] && $after['r3'] == 1 && !$after['texts'] && !$after['attachments']
                    && $after['t2posts'] == $after['t2real'] && $after['catposts'] == $after['catreal'] && $filesBefore == 2 && $filesAfter == 0;
                result('emptytrash', $ok, 'last=' . json_encode($last) . ' after=' . json_encode($after) . " files=$filesBefore->$filesAfter");
                // Command line: kunena:trash:purge (dry run, then for real)
                $setup2 = kexecJson('$db = JFactory::getDbo(); $db->setQuery("SELECT id FROM #__users WHERE username = \'admin\'"); $uid = (int) $db->loadResult(); $me = KunenaUserHelper::get($uid);
                    $cat = KunenaForumCategoryHelper::get(' . (int) $setup['cat'] . ');
                    list($t, $m) = $cat->newTopic(array("subject" => "CLI trash " . uniqid(), "message" => "CLI " . uniqid()), $me); $m->save();
                    return json_encode(array("t" => (int) $m->thread));');
                kexecJson('KunenaForumTopicHelper::get(' . (int) $setup2['t'] . ')->publish(KunenaForum::DELETED); return json_encode(1);');
                $dry = json_decode(shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php kunena:trash:purge --dry-run --format=json 2>/dev/null'), true);
                $still = kexecJson('return json_encode(KunenaForumTrash::count());');
                $real = json_decode(shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php kunena:trash:purge -n --format=json 2>/dev/null'), true);
                $gone = kexecJson('return json_encode(KunenaForumTrash::count());');
                $ok = !empty($dry['success']) && !empty($dry['data']['plan']) && $still['topics'] == 1
                    && !empty($real['success']) && isset($real['data']['deleted']['threads']) && $real['data']['deleted']['threads'] == 1 && $gone['topics'] == 0;
                result('emptytrash-cli', $ok, 'setup=' . json_encode($setup2) . ' dry=' . json_encode($dry) . ' real=' . json_encode($real) . ' after=' . json_encode($gone));
                break;
            case 'sampledata':
                // 1. Install from Tools > Sample Data (AJAX steps)
                $html = $admin->get('administrator/' . kurl('view=tools&layout=sampledata'));
                save('sampledata-page', $html);
                $url  = preg_match('#id="kunena-sampledata-install"[^>]*data-url="([^"]+)"#s', $html, $m) ? html_entity_decode($m[1]) : '';
                $t0   = microtime(true);
                $last = null;
                $rounds = 0;
                for ($i = 0, $stepName = 'init'; $url && $i < 200; $i++, $stepName = 'run') {
                    $rounds++;
                    $last = json_decode($admin->post('administrator/' . $url, array('step' => $stepName)), true);
                    if (!$last || empty($last['success']) || !empty($last['done'])) {
                        break;
                    }
                }
                $ok = $url && $last && !empty($last['success']) && !empty($last['done']);
                result('sample-install', $ok, sprintf('%.1fs, %d requests, last=%s', microtime(true) - $t0, $rounds, json_encode($last)));
                if (!$ok) {
                    break;
                }
                // 2. What was created, and are all counters right?
                $v = kexecJson(
                    <<<'PHP'
$db = JFactory::getDbo(); $q = function ($sql) use ($db) { $db->setQuery($sql); return (int) $db->loadResult(); };
$ids = function ($t) use ($db) { $db->setQuery("SELECT item_id FROM #__kunena_sampledata WHERE item_type=" . $db->quote($t)); return array_map('intval', $db->loadColumn()); };
$topics = implode(',', $ids('topic')); $users = implode(',', $ids('user')); $cats = implode(',', $ids('category'));
$db->setQuery("SELECT id FROM #__kunena_topics WHERE id IN ($topics) AND hold=0 ORDER BY posts DESC"); $long = (int) $db->loadResult();
$db->setQuery("SELECT category_id FROM #__kunena_topics WHERE id = $long"); $longCat = (int) $db->loadResult();
$db->setQuery("SELECT id FROM #__kunena_topics WHERE id IN ($topics) AND hold=0 AND posts BETWEEN 5 AND 12 ORDER BY id"); $mid = (int) $db->loadResult();
$db->setQuery("SELECT id FROM #__kunena_categories WHERE id IN ($cats) AND pub_access=6"); $staff = (int) $db->loadResult();
$files = $db->setQuery("SELECT folder, filename FROM #__kunena_attachments")->loadObjectList();
return json_encode(array('categories' => count($ids('category')), 'users' => count($ids('user')), 'topics' => count($ids('topic')),
  'posts' => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE thread IN ($topics)"),
  'topicMismatch' => $q("SELECT COUNT(*) FROM #__kunena_topics t WHERE t.id IN ($topics) AND t.hold=0 AND t.posts <> (SELECT COUNT(*) FROM #__kunena_messages m WHERE m.thread=t.id AND m.hold=0)"),
  'categoryMismatch' => $q("SELECT COUNT(*) FROM #__kunena_categories c WHERE c.id IN ($cats) AND (c.`numPosts` <> (SELECT COUNT(*) FROM #__kunena_messages m INNER JOIN #__kunena_topics t ON t.id=m.thread WHERE m.catid=c.id AND m.hold=0 AND t.hold=0) OR c.`numTopics` <> (SELECT COUNT(*) FROM #__kunena_topics t WHERE t.category_id=c.id AND t.hold=0 AND t.moved_id=0))"),
  'missingFiles' => count(array_filter($files, function ($f) { return !is_file(JPATH_ROOT . '/' . $f->folder . '/' . $f->filename); })),
  'attachments' => count($files), 'invalidEmails' => $q("SELECT COUNT(*) FROM #__users WHERE id IN ($users) AND email LIKE '%@example.invalid'"),
  'polls' => $q("SELECT COUNT(*) FROM #__kunena_topics WHERE id IN ($topics) AND poll_id>0"),
  'thankyous' => $q("SELECT COUNT(*) FROM #__kunena_thankyou"),
  'long' => $long, 'longCat' => $longCat, 'longPosts' => $q("SELECT posts FROM #__kunena_topics WHERE id=$long"), 'mid' => $mid, 'staff' => $staff,
  'cats' => $ids('category'), 'user' => min($ids('user'))));
PHP
                );
                $ok = isset($v['categories']) && $v['categories'] == 13 && $v['users'] == 20 && $v['topics'] == 60 && $v['posts'] == 389
                    && !$v['topicMismatch'] && !$v['categoryMismatch'] && !$v['missingFiles'] && $v['attachments'] >= 10 && $v['invalidEmails'] == 20 && $v['polls'] == 4 && $v['thankyous'] > 100;
                result('sample-data', $ok, json_encode(array_diff_key((array) $v, array('cats' => 1))));
                if (!$ok) {
                    break;
                }
                // 3. The frontend renders it (no PHP messages, no errors); the staff room is hidden from visitors
                $visitor = new JClient($base, "$out/$db-sample-visitor.jar");
                @unlink("$out/$db-sample-visitor.jar");
                $pages = array(kurl('view=category&layout=list'), kurl('view=topics&mode=replies'), kurl("view=user&userid={$v['user']}"),
                    kurl("view=topic&catid={$v['longCat']}&id={$v['long']}"), kurl("view=topic&catid={$v['longCat']}&id={$v['long']}&limitstart=" . max(0, $v['longPosts'] - 1)));
                foreach ($v['cats'] as $cid) {
                    if ($cid != $v['staff']) {
                        $pages[] = kurl("view=category&catid=$cid");
                    }
                }
                $bad = array();
                foreach ($pages as $p) {
                    $visitor->get($p);
                    list($pok, $pnote) = check($visitor, 'sampledata', 'sample-' . md5($p));
                    if (!$pok) {
                        $bad[] = "$p: $pnote";
                    }
                }
                $visitor->get(kurl('view=category&layout=list'));
                $staffHidden = strpos($visitor->last, 'Staff Room') === false;
                $fe->get(kurl('view=category&layout=list'));
                $staffShown = strpos($fe->last, 'Staff Room') !== false;
                result('sample-frontend', !$bad && $staffHidden && $staffShown, count($pages) . ' pages' . ($bad ? ' | ' . implode(' || ', array_slice($bad, 0, 3)) : '') . " staffHidden=$staffHidden staffShownToAdmin=$staffShown");
                // 4. A real user replies in a sample thread (separate request, like on a real site)
                $real = kexecJson('$db = JFactory::getDbo(); $db->setQuery("SELECT id FROM #__users WHERE username = \'admin\'"); $uid = (int) $db->loadResult(); $me = KunenaUserHelper::get($uid);
                    $m = KunenaForumMessageHelper::get(KunenaForumTopicHelper::get(' . (int) $v['mid'] . ')->first_post_id);
                    list(, $r) = $m->newReply(array("message" => "A real reply " . uniqid())); $r->userid = $me->userid; $r->name = $me->getName("");
                    if (!$r->save()) throw new Exception($r->getError()); return json_encode(array("id" => (int) $r->id, "cat" => (int) $m->catid));');
                // 5. Remove from Tools > Sample Data
                $html = $admin->get('administrator/' . kurl('view=tools&layout=sampledata'));
                $url  = preg_match('#id="kunena-sampledata-remove"[^>]*data-url="([^"]+)"#s', $html, $m) ? html_entity_decode($m[1]) : '';
                $last = null;
                for ($i = 0, $stepName = 'init'; $url && $i < 200; $i++, $stepName = 'run') {
                    $last = json_decode($admin->post('administrator/' . $url, array('step' => $stepName)), true);
                    if (!$last || empty($last['success']) || !empty($last['done'])) {
                        break;
                    }
                }
                $after = kexecJson('$db = JFactory::getDbo(); $q = function ($s) use ($db) { $db->setQuery($s); return (int) $db->loadResult(); };
                    return json_encode(array("users" => $q("SELECT COUNT(*) FROM #__users WHERE email LIKE \'%@example.invalid\'"),
                        "keptTopic" => $q("SELECT COUNT(*) FROM #__kunena_topics WHERE id=' . (int) $v['mid'] . '"),
                        "keptPosts" => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE thread=' . (int) $v['mid'] . '"),
                        "realReply" => $q("SELECT COUNT(*) FROM #__kunena_messages WHERE id=' . (int) ($real['id'] ?? 0) . '"),
                        "keptCategory" => $q("SELECT COUNT(*) FROM #__kunena_categories WHERE id=' . (int) ($real['cat'] ?? 0) . '"),
                        "sampleCategoriesLeft" => $q("SELECT COUNT(*) FROM #__kunena_categories WHERE id IN (' . implode(',', array_map('intval', $v['cats'])) . ')"),
                        "attachments" => $q("SELECT COUNT(*) FROM #__kunena_attachments"), "announcements" => $q("SELECT COUNT(*) FROM #__kunena_announcement"),
                        "orphanTexts" => $q("SELECT COUNT(*) FROM #__kunena_messages_text t LEFT JOIN #__kunena_messages m ON m.id=t.mesid WHERE m.id IS NULL"),
                        "orphanKusers" => $q("SELECT COUNT(*) FROM #__kunena_users ku LEFT JOIN #__users u ON u.id=ku.userid WHERE u.id IS NULL"),
                        "orphanPollOptions" => $q("SELECT COUNT(*) FROM #__kunena_polls_options o LEFT JOIN #__kunena_topics t ON t.poll_id=o.pollid WHERE t.id IS NULL"),
                        "tracking" => in_array($db->replacePrefix("#__kunena_sampledata"), $db->getTableList())));');
                $leftFiles = trim(shell_exec('find ' . escapeshellarg("$site/media/kunena") . ' -name "sample-*" | wc -l'));
                $ok = $last && !empty($last['done']) && !$after['users'] && $after['keptTopic'] == 1 && $after['keptPosts'] == 1 && $after['realReply'] == 1
                    && $after['keptCategory'] == 1 && $after['sampleCategoriesLeft'] >= 1 && $after['sampleCategoriesLeft'] <= 3 && !$after['attachments']
                    && !$after['announcements'] && !$after['orphanTexts'] && !$after['orphanKusers'] && !$after['orphanPollOptions'] && !$after['tracking'] && $leftFiles === '0';
                result('sample-remove', $ok, 'last=' . json_encode($last) . ' after=' . json_encode($after) . " files=$leftFiles");
                // 6. Command line
                $cli = function ($args) use ($site) {
                    return json_decode(shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php ' . $args . ' --format=json 2>/dev/null'), true);
                };
                $dry = $cli('kunena:sampledata:install --dry-run');
                $in  = $cli('kunena:sampledata:install -n');
                $dry2 = $cli('kunena:sampledata:remove --dry-run');
                $outc = $cli('kunena:sampledata:remove -n');
                $users = kexecJson('$db = JFactory::getDbo(); $db->setQuery("SELECT COUNT(*) FROM #__users WHERE email LIKE \'%@example.invalid\'"); return json_encode((int) $db->loadResult());');
                $ok = !empty($dry['success']) && !empty($dry['data']['plan']) && !empty($in['success']) && ($in['data']['installed']['posts'] ?? 0) == 389
                    && !empty($dry2['data']['plan']) && !empty($outc['success']) && $users === 0;
                result('sample-cli', $ok, 'install=' . json_encode($in['messages'] ?? $in) . ' remove=' . json_encode($outc['messages'] ?? $outc) . ' usersLeft=' . json_encode($users));
                break;
            case 'extras':
                // The modules and plugins kunena.org used to distribute separately, now in the package
                $x = kexecJson(
                    <<<'PHP'
$db = JFactory::getDbo();
$db->setQuery("SELECT type, folder, element, enabled, manifest_cache FROM #__extensions WHERE element IN ('mod_kunenalatest','mod_kunenalogin','mod_kunenasearch','mod_kunenastats') OR (type='plugin' AND ((folder='content' AND element='kunenadiscuss') OR (folder='search' AND element='kunena') OR (folder='kunena' AND element='baldrick')))");
$rows = array();
foreach ($db->loadObjectList() as $r) { $m = json_decode($r->manifest_cache); $rows[$r->type . ':' . $r->folder . ':' . $r->element] = array('enabled' => (int) $r->enabled, 'version' => $m ? $m->version : null); }
return json_encode($rows);
PHP
                );
                $versions = array_unique(array_map(function ($r) {
                    return $r['version'];
                }, (array) $x));
                $ok = count((array) $x) == 7 && array_values($versions) == array('5.3.0') && $x['plugin:content:kunenadiscuss']['enabled'] == 1 && $x['plugin:search:kunena']['enabled'] == 1 && $x['plugin:kunena:baldrick']['enabled'] == 1;
                result('extras-installed', $ok, json_encode($x));
                // Modules: one published instance of each in the "sidebar" position
                kexecJson(
                    <<<'PHP'
$db = JFactory::getDbo();
foreach (array('mod_kunenalatest', 'mod_kunenalogin', 'mod_kunenasearch', 'mod_kunenastats') as $i => $module) {
    $table = JTable::getInstance('Module');
    $table->bind(array('title' => "Test $module", 'module' => $module, 'position' => 'sidebar', 'published' => 1, 'access' => 1, 'client_id' => 0, 'language' => '*', 'ordering' => $i + 1, 'showtitle' => 1, 'params' => '{}', 'note' => ''));
    if (!$table->store()) throw new Exception($table->getError());
    $db->setQuery($db->getQuery(true)->insert('#__modules_menu')->columns(array('moduleid', 'menuid'))->values((int) $table->id . ', 0'))->execute();
}
JFactory::getCache()->clean('com_modules');
return json_encode(1);
PHP
                );
                $visitor = new JClient($base, "$out/$db-extras-visitor.jar");
                @unlink("$out/$db-extras-visitor.jar");
                $bad = array();
                foreach (array('index.php', kurl('view=category&layout=list')) as $p) {
                    $visitor->get($p);
                    list($pok, $pnote) = check($visitor, 'extras', 'extras-modules-' . md5($p));
                    foreach (array('klatest', 'klogin', 'ksearch', 'kstats') as $marker) {
                        if (strpos($visitor->last, $marker) === false) {
                            $pok = false;
                            $pnote .= " missing $marker";
                        }
                    }
                    if (!$pok) {
                        $bad[] = "$p: $pnote";
                    }
                }
                $fe->get('index.php');
                list($pok, $pnote) = check($fe, 'extras', 'extras-modules-admin');
                if (!$pok || strpos($fe->last, 'klatest') === false) {
                    $bad[] = "logged in: $pnote";
                }
                result('extras-modules', !$bad, implode(' || ', $bad));
                // Search plugin: com_search finds forum posts
                $visitor->get('index.php?option=com_search&searchword=Kunena&searchphrase=any&areas[0]=kunena');
                list($pok, $pnote) = check($visitor, 'extras', 'extras-search');
                result('extras-search', $pok && stripos($visitor->last, 'option=com_kunena') !== false, $pnote);
                // Kunena Discuss: an article with a discussion form; posting creates the forum thread
                $d = kexecJson(
                    <<<'PHP'
$db = JFactory::getDbo();
$cat = null;
foreach (KunenaForumCategoryHelper::getCategories(false, false, 'none') as $c) { if (!$c->isSection() && !$c->locked && $c->pub_access == 1) { $cat = $c; } }
$p = new Joomla\Registry\Registry($cat->params); $p->set('access_post', array(1, 2, 6, 8, 9)); $p->set('access_reply', array(1, 2, 6, 8, 9)); $cat->params = $p->toString(); $cat->save();
$config = KunenaConfig::getInstance(); $config->pubwrite = 1; $config->captcha = -1; $config->floodprotection = 0; $config->save();
$plugin = JTable::getInstance('Extension'); $plugin->load(array('type' => 'plugin', 'folder' => 'content', 'element' => 'kunenadiscuss'));
$params = new Joomla\Registry\Registry($plugin->params); $params->set('default_category', (int) $cat->id); $params->set('form', 1); $params->set('create', 0); $params->set('show_article_pages', 2);
$plugin->params = $params->toString(); $plugin->enabled = 1; $plugin->store();
$article = JTable::getInstance('Content');
$article->bind(array('title' => 'Discussed article ' . uniqid(), 'alias' => 'discussed-article-' . uniqid(), 'introtext' => '<p>An article to discuss.</p>', 'fulltext' => '', 'state' => 1, 'catid' => 2, 'access' => 1, 'language' => '*', 'created' => JFactory::getDate()->toSql(), 'publish_up' => JFactory::getDate('-1 day')->toSql(), 'metadesc' => '', 'metakey' => '', 'images' => '{}', 'urls' => '{}', 'attribs' => '{}', 'metadata' => '{}'));
if (!$article->store()) throw new Exception($article->getError());
JFactory::getCache()->clean('com_plugins');
return json_encode(array('article' => (int) $article->id, 'cat' => (int) $cat->id));
PHP
                );
                $articleUrl = 'index.php?option=com_content&view=article&id=' . (int) ($d['article'] ?? 0) . '&catid=2';
                $topicsIn = function () use ($d) {
                    return kexecJson('$db = JFactory::getDbo(); $db->setQuery("SELECT COUNT(*) FROM #__kunena_topics WHERE category_id=' . (int) ($d['cat'] ?? 0) . '"); $t = (int) $db->loadResult(); $db->setQuery("SELECT COUNT(*) FROM #__kunena_messages WHERE catid=' . (int) ($d['cat'] ?? 0) . '"); return json_encode(array((int) $t, (int) $db->loadResult()));');
                };
                $post = function ($client, $label, $message) use ($articleUrl, $d) {
                    $html = $client->get($articleUrl);
                    save("extras-discuss-$label-form", $html);
                    $hasForm = strpos($html, 'kdiscussContentId') !== false;
                    $client->post($articleUrl, array('name' => 'Discusser', 'email' => 'discusser@example.com', 'message' => $message, 'kdiscussContentId' => (int) $d['article'], JClient::token($html) => 1));
                    save("extras-discuss-$label-post", $client->last);

                    return $hasForm;
                };
                $before = $topicsIn();
                $hasForm = $post($fe, 'admin', 'Discussion reply ' . uniqid());
                $after = $topicsIn();
                list($pok, $pnote) = check($fe, 'extras', 'extras-discuss-admin-check');
                result('extras-discuss', $hasForm && $pok && $after[0] == $before[0] + 1 && $after[1] >= $before[1] + 1, "form=$hasForm before=" . json_encode($before) . ' after=' . json_encode($after) . " $pnote");
                // Guests must solve the CAPTCHA selected in Global Configuration (IconCaptcha), nothing is created otherwise
                shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php config:set captcha=iconcaptcha -n -q && php cli/joomla.php extension:enable plg_captcha_iconcaptcha -n -q');
                kexecJson('$c = KunenaConfig::getInstance(); $c->captcha = 1; $c->save(); return json_encode(1);');
                sleep(3);
                $guest = new JClient($base, "$out/$db-extras-guest.jar");
                @unlink("$out/$db-extras-guest.jar");
                $before = $topicsIn();
                $guestForm = $post($guest, 'guest', 'Bot spam ' . uniqid());
                $after = $topicsIn();
                $captchaShown = stripos(file_get_contents("$out/$db-extras-discuss-guest-form.html"), 'iconcaptcha') !== false;
                shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php config:set captcha=0 -n -q');
                kexecJson('$c = KunenaConfig::getInstance(); $c->captcha = -1; $c->save(); return json_encode(1);');
                result('extras-discuss-captcha', $guestForm && $captchaShown && $after == $before, "form=$guestForm captchaShown=$captchaShown before=" . json_encode($before) . ' after=' . json_encode($after));
                break;
            case 'moderate':
                if (empty($state['topic'])) {
                    result('moderate', false, 'no topic');
                    break;
                }
                $tid = $state['topic'];
                $cats = $state['cats'];
                $cid = end($cats);
                $html = $fe->get(kurl("view=topic&catid=$cid&id=$tid"));
                $t = JClient::token($html);
                // delete topic
                $fe->get(kurl("view=topic&task=delete&catid=$cid&id=$tid&$t=1"));
                list($ok, $note) = check($fe, 'moderate', 'mod-delete');
                result('mod-delete', $ok, $note);
                $fe->get(kurl("view=topic&task=undelete&catid=$cid&id=$tid&$t=1"));
                list($ok, $note) = check($fe, 'moderate', 'mod-undelete');
                result('mod-undelete', $ok, $note);
                // move topic to another category
                $target = $cats[0] == $cid && isset($cats[1]) ? $cats[1] : $cats[0];
                $html = $fe->get(kurl("view=topic&layout=moderate&catid=$cid&id=$tid"));
                save('moderate-form', $html);
                list($ok0, $note0) = check($fe, 'moderate', 'moderate-form');
                $h = JClient::hidden($html, 'id="myform"');
                $t = JClient::token($html);
                $fe->post(kurl('view=topic'), array_merge($h, array('task' => 'move', 'catid' => $cid, 'id' => $tid, 'targetcategory' => $target, 'targettopic' => 0, 'subject' => $state['subject'], $t => 1)));
                list($ok, $note) = check($fe, 'moderate', 'mod-move');
                result('mod-move', $ok && $ok0, $note0 . ' ' . $note . " -> cat $target");
                $state['movedcat'] = $target;
                // merge: move the topic into another (new) topic
                $other = newTopic($fe, $cid, "Merge target on $db " . date('His'), "Merge target body $db " . date('His'));
                $html = $fe->get(kurl("view=topic&layout=moderate&catid=$target&id=$tid"));
                $h = JClient::hidden($html, 'id="myform"');
                $t = JClient::token($html);
                $fe->post(kurl('view=topic'), array_merge($h, array('task' => 'move', 'catid' => $target, 'id' => $tid, 'targetcategory' => $cid, 'targettopic' => $other, $t => 1)));
                list($ok, $note) = check($fe, 'moderate', 'mod-merge');
                $fe->get(kurl("view=topic&catid=$cid&id=$other"));
                list($ok2, $note2) = check($fe, 'moderate', 'mod-merged-view', 'A reply on');
                result('mod-merge', $ok && $ok2 && $other, "$note | $note2 | into topic $other");
                $state['topic'] = $other;
                // permanent delete: soft-delete a new topic, then purge it from the backend trash
                $victim = newTopic($fe, $cid, "Purge me on $db " . date('His'), "Purge body $db " . date('His'));
                $html = $fe->get(kurl("view=topic&catid=$cid&id=$victim"));
                $t = JClient::token($html);
                $fe->get(kurl("view=topic&task=delete&catid=$cid&id=$victim&$t=1"));
                $html = $admin->get('administrator/' . kurl('view=trash'));
                $t = JClient::token($html);
                $admin->post('administrator/' . kurl('view=trash'), array('task' => 'purge', 'type' => 'topics', 'cid[]' => $victim, 'boxchecked' => 1, $t => 1));
                $html = $admin->last;
                $t = JClient::token($html);
                $admin->post('administrator/' . kurl('view=trash'), array('task' => 'purge', 'md5' => md5(serialize(array($victim))), $t => 1));
                list($ok, $note) = check($admin, 'moderate', 'mod-purge');
                $fe->get(kurl("view=topic&catid=$cid&id=$victim"));
                $gone = $fe->code >= 400 || stripos($fe->last, "Purge me on $db") === false;
                result('mod-purge', $ok && $victim && $gone, "$note | topic $victim " . ($gone ? 'gone' : 'still there'));
                // topics moderation list
                $fe->get(kurl('view=topics&layout=default&mode=unapproved'));
                list($ok, $note) = check($fe, 'moderate', 'mod-unapproved');
                result('mod-list', $ok, $note);
                break;
            case 'baldrick':
                // Baldrick AI against a mock provider (see baldrick.inc.php)
                include __DIR__ . '/baldrick.inc.php';
                break;
            case 'adminpages':
                foreach (array('categories', 'users', 'attachments', 'smilies', 'ranks', 'templates', 'plugins', 'logs', 'baldrick', 'tools', 'statistics', 'trash', 'trash&layout=default&type=messages', 'categories&layout=edit&catid=2', 'tools&layout=report', 'tools&layout=diagnostics', 'tools&layout=prune', 'tools&layout=syncusers', 'tools&layout=recount', 'tools&layout=menu', 'tools&layout=purgerestatements', 'tools&layout=cleanupip') as $v) {
                    $admin->get('administrator/' . kurl("view=$v"));
                    list($ok, $note) = check($admin, 'adminpages', "adm-$v");
                    result("adm-$v", $ok, $note);
                }
                $admin->get('administrator/' . kurl('view=tools&layout=recount'));
                break;
            case 'tools':
                // Recount statistics (AJAX loop as the backend does it)
                $html = $admin->get('administrator/' . kurl('view=tools&layout=recount'));
                $t = JClient::token($html);
                $r = $admin->post('administrator/' . kurl('view=tools&format=json'), array('task' => 'recount', 'topics' => 1, 'usertopics' => 1, 'categories' => 1, 'users' => 1, 'polls' => 1, $t => 1));
                $ok = false;
                $note = '';
                for ($i = 0; $i < 50; $i++) {
                    $j = json_decode($r, true);
                    if (!$j) {
                        $note = 'non-JSON: ' . substr(strip_tags($r), 0, 400);
                        break;
                    }
                    if (empty($j['success'])) {
                        $note = 'error: ' . json_encode($j);
                        break;
                    }
                    if (empty($j['href']) || strpos($j['href'], 'dorecount') === false) {
                        $ok = true;
                        break;
                    }
                    $r = $admin->get(preg_replace('#^/#', '', str_replace('&amp;', '&', $j['href'])) . (strpos($j['href'], 'format=json') === false ? '&format=json' : ''));
                }
                result('tools-recount', $ok, $note);
                // Diagnostics: run every fix and delete query (nothing to repair on a healthy forum, but the SQL runs)
                copy(__DIR__ . '/kexec.php', "$site/kexec.php");
                $r = (new JClient($base, "$out/$db-kexec.jar"))->post('kexec.php', array('c' => '$bad = array(); foreach (KunenaForumDiagnostics::getList() as $i) { try { KunenaForumDiagnostics::count($i); KunenaForumDiagnostics::getItems($i); if (KunenaForumDiagnostics::canFix($i) && !KunenaForumDiagnostics::fix($i)) $bad[] = "fix $i"; if (KunenaForumDiagnostics::canDelete($i) && !KunenaForumDiagnostics::delete($i)) $bad[] = "delete $i"; } catch (Exception $e) { $bad[] = "$i: " . $e->getMessage(); } } return $bad ? implode(" || ", $bad) : "ok";'));
                result('tools-diagnostics', strpos($r, '"ok"') !== false, substr(trim(strip_tags($r)), 0, 1500));
                foreach (array('syncusers' => array('useradd' => 1, 'userrename' => 1, 'userdel' => 1, 'userdellife' => 1), 'prune' => array('prune_forum[]' => 2, 'prune_days' => 3650, 'trashdelete' => 0, 'controloptions' => 'all', 'keepsticky' => 1), 'cleanupIP' => array('cleanup_ip_days' => 3650), 'purgeReStatements' => array('re_string' => 'Re:')) as $task => $fields) {
                    $html = $admin->get('administrator/' . kurl('view=tools'));
                    $t = JClient::token($html);
                    $admin->post('administrator/' . kurl('view=tools'), array_merge($fields, array('task' => $task, $t => 1)));
                    list($ok, $note) = check($admin, 'tools', "tools-$task");
                    result("tools-$task", $ok, $note);
                }
                break;
            case 'purge':
                // Kunena > Tools > Uninstall: drops every Kunena table, then uninstalls the extension
                $html = $admin->get('administrator/' . kurl('view=tools&layout=uninstall'));
                $t = JClient::token($html);
                $admin->post('administrator/' . kurl('view=tools'), array('task' => 'uninstall', 'username' => $env['ADMIN_USER'], 'password' => $env['ADMIN_PASS'], $t => 1));
                list($ok, $note) = check($admin, 'purge', 'purge');
                $left = trim(shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php extension:list "*kunena*" 2>/dev/null | grep -c "| kunena\\|com_kunena"'));
                result('purge', $ok, $note . " | url={$admin->lastUrl} | kunena extensions left: $left");
                break;
            case 'uninstall':
                $admin->get('administrator/index.php?option=com_installer&view=manage&filter[search]=kunena');
                $html = $admin->last;
                preg_match_all('#name="cid\[\]" value="(\d+)"#', $html, $m);
                $id = null;
                $rows = preg_split('#<tr#', $html);
                foreach ($rows as $r) {
                    if (preg_match('#cid\[\]" value="(\d+)"#', $r, $mm) && preg_match('#>\s*Kunena Forum\s*<|com_kunena#i', $r)) {
                        $id = $mm[1];
                    }
                }
                if (!$id) {
                    $id = trim(shell_exec("cd " . escapeshellarg($site) . " && php cli/joomla.php extension:list --format=json 2>/dev/null | php -r '\$j=json_decode(stream_get_contents(STDIN),true); foreach((\$j[\"extensions\"]??\$j) as \$e){ if((\$e[\"element\"]??\"\")===\"com_kunena\") echo \$e[\"extension_id\"]??\$e[\"id\"]; }'"));
                }
                $t = JClient::token($html);
                $admin->post('administrator/index.php?option=com_installer&view=manage', array('task' => 'manage.remove', 'cid[]' => $id, 'boxchecked' => 1, $t => 1));
                list($ok, $note) = check($admin, 'uninstall', 'uninstall');
                $ok = $ok && stripos($admin->last, 'successful') !== false;
                result('uninstall', $ok, "$note ext=$id");
                break;
        }
    } catch (Throwable $e) {
        result($step, false, 'exception: ' . $e->getMessage());
    }
    file_put_contents("$out/$db-state.json", json_encode($state));
}
// PHP log lines from Kunena files
$log = "$site/php_errors.log";
if (is_file($log)) {
    $lines = array_filter(file($log), function ($l) {
        return preg_match('#/(components/com_kunena|libraries/kunena|plugins/[a-z]+/kunena|plugins/kunena)/#', $l);
    });
    $lines = array_unique(array_map(function ($l) {
        return preg_replace('/^\[[^\]]+\] /', '', trim($l));
    }, $lines));
    echo "--- PHP messages from Kunena files: " . count($lines) . "\n" . implode("\n", array_slice($lines, 0, 40)) . "\n";

    // KUNENA_ALLOW_PHP_MESSAGES=1: e.g. while setting up an old Kunena release for the upgrade test
    if ($lines && !getenv('KUNENA_ALLOW_PHP_MESSAGES')) {
        $results['php-messages'] = array(false, count($lines) . ' PHP messages from Kunena files');
    }
}
clearstatcache();
if (is_file($dblog) && filesize($dblog) > $dblogStart) {
    $new = preg_split('/\n(?=\d{4}-\d\d-\d\dT)/', substr(file_get_contents($dblog), $dblogStart));
    $new = array_filter(array_map(function ($l) {
        return trim(preg_replace('/\s+/', ' ', $l));
    }, $new), function ($l) {
        return $l !== '' && $l[0] !== '#';
    });
    echo "--- Failed queries logged: " . count($new) . "\n" . implode("\n", array_slice(array_unique(array_map(function ($l) {
        return substr(preg_replace('/^\S+\s+\S+\s+\S+\s+\S+\s+/', '', $l), 0, 900);
    }, $new)), 0, 60)) . "\n";
    $results['db-errors'] = array(false, count($new) . ' failed queries');
}
$fail = count(array_filter($results, function ($r) {
    return !$r[0];
}));
echo "== $db: " . (count($results) - $fail) . " passed, $fail failed\n";
exit($fail ? 1 : 0);

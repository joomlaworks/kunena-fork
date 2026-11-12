<?php

// Baldrick AI scenarios (included by run.php, step "baldrick"): a mock AI provider (baldrick-mock.php) stands in for
// Anthropic- and OpenAI-compatible APIs; e-mails are captured with a fake sendmail. No real API is ever contacted.

$mockPort = 8190 + ($ports[$db] - 8100);
$mockLog  = "$out/$db-baldrick-mock.jsonl";
$mailLog  = "$out/$db-baldrick-mail.log";
$mockUrl  = "http://127.0.0.1:$mockPort/";
@unlink($mockLog);
@unlink($mailLog);

shell_exec('pkill -f ' . escapeshellarg("php -S 127.0.0.1:$mockPort") . ' 2>/dev/null');
shell_exec('BALDRICK_MOCK_LOG=' . escapeshellarg($mockLog) . ' BALDRICK_MOCK_SITE=' . escapeshellarg($base)
    . ' nohup php -S 127.0.0.1:' . $mockPort . ' ' . escapeshellarg(__DIR__ . '/baldrick-mock.php') . ' > ' . escapeshellarg("$out/$db-baldrick-mock.out") . ' 2>&1 &');
usleep(500000);

// Fake sendmail: the e-mails Joomla sends land in $mailLog
file_put_contents("$out/fake-sendmail-$db.sh", "#!/bin/sh\ncat >> " . escapeshellarg($mailLog) . "\necho '----MAIL-END----' >> " . escapeshellarg($mailLog) . "\n");
chmod("$out/fake-sendmail-$db.sh", 0755);
foreach (array('mailonline=1', 'mailer=sendmail', 'sendmail=' . "$out/fake-sendmail-$db.sh", 'mailfrom=forum@example.com') as $setting) {
    shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php config:set ' . escapeshellarg($setting) . ' -n -q 2>&1');
}
sleep(2);

$cli = function ($command) use ($site) {
    return shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php ' . $command . ' 2>&1');
};

/** Sets Baldrick's settings (merged into the saved ones) */
$configure = function (array $values) {
    return kexecJson('$p = KunenaBaldrick::getPlugin(true); $r = new Joomla\\Registry\\Registry($p->params); foreach (json_decode(' . var_export(json_encode($values), true) . ', true) as $k => $v) { $r->set($k, $v); } $db = JFactory::getDbo(); $db->setQuery("UPDATE #__extensions SET params = " . $db->quote($r->toString()) . " WHERE extension_id = " . (int) $p->extension_id)->execute(); return json_encode("ok");');
};

$queueOf = function ($topicId) {
    return kexecJson('$db = JFactory::getDbo(); return json_encode($db->setQuery("SELECT status, reason, attempts, result FROM #__kunena_baldrick_queue WHERE topic_id = ' . (int) $topicId . ' ORDER BY id DESC")->loadAssocList());');
};

$logOf = function ($topicId) {
    return kexecJson('$db = JFactory::getDbo(); return json_encode($db->setQuery("SELECT verdict, action, models, input_tokens, output_tokens, cost, reply_id, dry_run, message FROM #__kunena_baldrick_log WHERE topic_id = ' . (int) $topicId . ' ORDER BY id DESC")->loadAssocList());');
};

/** Baldrick's posts in a thread (BBCode) and the thread's state */
$threadOf = function ($topicId) {
    return kexecJson('$t = KunenaForumTopicHelper::get(' . (int) $topicId . ', true); $uid = KunenaBaldrick::getUserId(); $db = JFactory::getDbo(); $rows = $uid ? $db->setQuery("SELECT m.id, m.hold, t.message FROM #__kunena_messages AS m INNER JOIN #__kunena_messages_text AS t ON t.mesid = m.id WHERE m.thread = ' . (int) $topicId . ' AND m.userid = " . (int) $uid)->loadAssocList() : array(); $holds = $db->setQuery("SELECT id, hold FROM #__kunena_messages WHERE thread = ' . (int) $topicId . ' ORDER BY id")->loadAssocList("id", "hold"); return json_encode(array("hold" => (int) $t->hold, "posts" => (int) $t->posts, "baldrick" => $rows, "holds" => $holds));');
};

$step = 'baldrick';

// 1. Installed with the package, enabled, without a key: the notice at the top of Kunena's administrator pages
$x = kexecJson('$p = KunenaBaldrick::getPlugin(true); $m = JFactory::getDbo()->setQuery("SELECT manifest_cache FROM #__extensions WHERE extension_id = " . (int) $p->extension_id)->loadResult(); return json_encode(array("id" => (int) $p->extension_id, "enabled" => (int) $p->enabled, "version" => json_decode($m)->version, "configured" => KunenaBaldrick::isConfigured(), "tables" => KunenaBaldrick::hasTables()));');
$pluginId = isset($x['id']) ? (int) $x['id'] : 0;
result('bk-installed', $pluginId && $x['enabled'] === 1 && $x['version'] === '5.3.0' && !$x['configured'] && $x['tables'], json_encode($x));

$admin->get('administrator/' . kurl('view=cpanel'));
list($ok, $note) = check($admin, $step, 'bk-notice', 'Baldrick AI is active but has no API key');
result('bk-notice', $ok, $note);

// 2. The settings page: one tab, section headers, model fields with their buttons
$admin->get('administrator/index.php?option=com_plugins&task=plugin.edit&extension_id=' . $pluginId);
list($ok, $note) = check($admin, $step, 'bk-settings', 'Fetch models');
$html  = $admin->last;
$ok    = $ok && substr_count($html, 'field-spacer') === 7 && strpos($html, 'Step 1: triage model') !== false && substr_count($html, 'baldrick-model"') === 2
    && strpos($html, 'Advanced: processing, limits and timeouts') !== false && strpos($html, 'Answer response timeout') !== false
    && strrpos($html, 'Advanced: processing') > strrpos($html, 'Notify these addresses')
    && strpos($html, 'name="jform[params][signature]"') === false && strpos($html, 'search the forum for similar threads') !== false
    && !preg_match('#<a[^>]+data-toggle="tab"[^>]*>[^<]*(Step|When Baldrick)#', $html);
result('bk-settings-page', $ok, $note);
$token = JClient::token($html);
$admin->get('administrator/index.php?option=com_plugins&view=plugins');

// 3. Fetch models / test connection (values not saved yet, as sent by the settings page)
$ajax = function ($task, array $data) use ($admin, $token) {
    $r = $admin->post('administrator/' . kurl('view=baldrick&format=json&task=' . $task), $data + array($token => 1));

    return json_decode($r, true) ?: array('raw' => substr(strip_tags($r), 0, 400));
};
$r = $ajax('models', array('stage' => 'triage', 'provider' => 'anthropic', 'base_url' => $mockUrl . 'anthropic', 'api_key' => 'test-key-a'));
result('bk-models-anthropic', !empty($r['success']) && count($r['models']) === 3 && $r['models'][0]['id'] === 'mock-haiku' && $r['models'][0]['name'] === 'Mock Haiku', json_encode($r));
$r = $ajax('models', array('stage' => 'answer', 'provider' => 'openai', 'base_url' => $mockUrl . 'openai/v1', 'api_key' => 'test-key-o'));
result('bk-models-openai', !empty($r['success']) && count($r['models']) === 3, json_encode($r));
$r = $ajax('models', array('stage' => 'triage', 'provider' => 'openai', 'base_url' => $mockUrl . 'openai/v1', 'api_key' => ''));
result('bk-models-nokey', empty($x['configured']) ? empty($r['success']) && stripos($r['message'], 'API key') !== false : !empty($r['success']), json_encode($r));
$r = $ajax('models', array('stage' => 'triage', 'provider' => 'openai', 'base_url' => 'file:///etc/passwd', 'api_key' => 'x'));
result('bk-models-badurl', empty($r['success']), json_encode($r));
// Anthropic-compatible endpoint without a model list (Xiaomi MiMo): the OpenAI-compatible list next to it
$r = $ajax('models', array('stage' => 'triage', 'provider' => 'anthropic', 'base_url' => $mockUrl . 'mimo/anthropic', 'api_key' => 'test-key-m', 'refresh' => 1));
result('bk-models-fallback', !empty($r['success']) && count($r['models']) === 3 && strpos($r['message'], 'mimo/v1/models') !== false, json_encode($r));
$r = $ajax('test', array('stage' => 'triage', 'provider' => 'anthropic', 'base_url' => $mockUrl . 'mimo/anthropic', 'api_key' => 'test-key-m', 'model' => 'mock-haiku'));
result('bk-test-mimo', !empty($r['success']), json_encode($r));
$r = $ajax('test', array('stage' => 'triage', 'provider' => 'anthropic', 'base_url' => $mockUrl . 'anthropic', 'api_key' => 'test-key-a', 'model' => 'mock-haiku'));
result('bk-test-connection', !empty($r['success']) && strpos($r['message'], 'mock-haiku') !== false, json_encode($r));
$r = $admin->post('administrator/' . kurl('view=baldrick&format=json&task=models'), array('stage' => 'triage', 'provider' => 'openai', 'base_url' => $mockUrl . 'openai/v1', 'api_key' => 'k'));
result('bk-models-token', strpos($r, '"success":false') !== false, substr(strip_tags($r), 0, 200));

// 4. Configure: triage on the Anthropic-style mock, answers on the OpenAI-style mock, processing from cron
$configure(array(
    'triage_provider' => 'anthropic', 'triage_base_url' => $mockUrl . 'anthropic', 'triage_api_key' => 'test-key-a', 'triage_model' => 'mock-haiku',
    'triage_price_in' => '1', 'triage_price_out' => '5',
    'answer_same_connection' => 0, 'answer_provider' => 'openai', 'answer_base_url' => $mockUrl . 'openai/v1', 'answer_api_key' => 'test-key-o',
    'answer_model' => 'mock-sonnet', 'answer_price_in' => '3', 'answer_price_out' => '15',
    'processing' => 'cron', 'dry_run' => 1, 'inactivity_days' => 7, 'replies_per_topic' => 1, 'daily_cap' => 50, 'min_length' => 10,
    'notify_emails' => 'baldrick-watch@example.com, not-an-address', 'notify_users' => array(), 'spam_action' => 'unpublish', 'spam_trusted_posts' => 10,
    'translate_english' => 1, 'public_only' => 1, 'categories_include' => array(), 'categories_exclude' => array(), 'user_id' => '',
));
$admin->get('administrator/' . kurl('view=cpanel'));
result('bk-notice-gone', strpos($admin->last, 'Baldrick AI is active but has no API key') === false, '');

// A category open to registered users, and a related thread (by the administrator) for retrieval
$c = kexecJson('$public = KunenaBaldrick::getPublicCategories(); foreach (KunenaForumCategoryHelper::getCategories(false, false, "none") as $c) { if ($c->parent_id && $c->published == 1 && !$c->locked && in_array((int) $c->id, $public, true)) { return json_encode((int) $c->id); } } return json_encode(0);');
$cid = (int) $c;
result('bk-category', $cid > 0, json_encode($c));
$relatedId = newTopic($fe, $cid, 'Frobnicator widget setup guide', 'To set up the frobnicator widget, open its settings and press Save. Works with the frobnicator setup on every site.');

// A registered member who asks the questions
$member = kexecJson('$db = JFactory::getDbo(); $id = (int) $db->setQuery("SELECT id FROM #__users WHERE username = " . $db->quote("asker"))->loadResult(); if (!$id) { $u = new JUser(); $d = array("name" => "Asker", "username" => "asker", "email" => "asker@example.com", "password" => "Asker12345678", "password2" => "Asker12345678", "groups" => array(2), "block" => 0); $u->bind($d); $u->save(); $id = (int) $u->id; } return json_encode($id);');
$mc = new JClient($base, "$out/$db-asker.jar");
@unlink("$out/$db-asker.jar");
$mc->get('index.php');
$mc->siteLogin('asker', 'Asker12345678');

// 5. Dry run: a member's thread is ignored, an administrator's thread is answered
$t1 = newTopic($mc, $cid, 'Member question in dry run', 'How do I make the frobnicator widget work in dry run mode please?');
$t2 = newTopic($fe, $cid, 'Frobnicator widget error', 'My frobnicator widget shows an error after saving the settings, how do I fix it?');
$q1 = $queueOf($t1);
$q2 = $queueOf($t2);
result('bk-dryrun-queue', $t1 && $t2 && !$q1 && count($q2) === 1 && $q2[0]['status'] === 'pending' && $q2[0]['reason'] === 'new', json_encode(array($t1, $q1, $t2, $q2)));

$out1 = $cli('kunena:baldrick:process --dry-run -n');
$q2b  = $queueOf($t2);
result('bk-cli-dryrun', strpos($out1, 'Threads due') !== false && $q2b[0]['status'] === 'pending', trim(substr($out1, 0, 300)));

$out2 = $cli('kunena:baldrick:process -n');
$th2  = $threadOf($t2);
$lg2  = $logOf($t2);
$text = isset($th2['baldrick'][0]['message']) ? $th2['baldrick'][0]['message'] : '';
$ok   = count($th2['baldrick']) === 1 && $th2['baldrick'][0]['hold'] == 0 && strpos($text, 'MOCK-ANSWER') !== false
    && strpos($text, 'RELATED-FOUND') !== false && strpos($text, 'Replied by Baldrick AI') === false
    && strpos($text, '[img]') === false && strpos($text, '<script>') === false
    && strpos($text, '[url=index.php?option=com_kunena&view=category&layout=list]') !== false && strpos($text, '127.0.0.1') === false
    && $lg2[0]['action'] === 'replied' && $lg2[0]['dry_run'] == 1 && $lg2[0]['models'] === 'mock-haiku > mock-sonnet' && $lg2[0]['cost'] > 0;
result('bk-dryrun-answer', $ok, json_encode(array($out2, $th2, $lg2)));

// The requests the mock received: keys, API versions, the prompts' structure; no private category content
$reqs = array_map(function ($l) {
    return json_decode($l, true);
}, file($mockLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
$msgs = array_values(array_filter($reqs, function ($r) {
    return substr($r['path'], -9) === '/messages' || substr($r['path'], -17) === '/chat/completions';
}));
$last2 = array_slice($msgs, -2);
$ok    = count($last2) === 2 && $last2[0]['headers']['x-api-key'] === 'test-key-a' && $last2[0]['headers']['anthropic-version'] === '2023-06-01'
    && !isset($last2[0]['body']['output_config']) && !isset($last2[0]['body']['fallbacks'])
    && $last2[1]['headers']['authorization'] === 'Bearer test-key-o' && isset($last2[1]['body']['max_tokens']) && !isset($last2[1]['body']['response_format'])
    && strpos(json_encode($last2[1]['body']), 'related_threads') !== false && strpos(json_encode($last2[1]['body']), 'Frobnicator widget setup guide') !== false;
result('bk-requests', $ok, json_encode(array_map(function ($r) {
    return array($r['path'], $r['headers'], array_keys((array) $r['body']));
}, $last2)));

// The thread page shows the reply with a routed link
$fe->get(kurl("view=topic&catid=$cid&id=$t2"));
list($ok, $note) = check($fe, $step, 'bk-thread', 'MOCK-ANSWER');
result('bk-thread-page', $ok && strpos($fe->last, 'Replied by Baldrick AI, my lord, using a cunning model (...and do remember that AI can make mistakes, so double check responses)') !== false && strpos($fe->last, 'evil.example') === false, $note);

// The notification e-mail: first post and last posts, newest first, plain text
clearstatcache();
$mail = is_file($mailLog) ? file_get_contents($mailLog) : '';
$ok   = strpos($mail, 'baldrick-watch@example.com') !== false && strpos($mail, 'not-an-address') === false && strpos($mail, 'Baldrick answered: Frobnicator widget error') !== false;
$decoded = '';
if (preg_match_all("#\r?\n\r?\n(.*?)----MAIL-END----#s", $mail, $mm)) {
    $decoded = base64_decode(preg_replace('/\s+/', '', end($mm[1])));
}
$ok = $ok && strpos($decoded, 'Content-Type: text/html') === false && strpos($decoded, '#2 ') !== false && strpos($decoded, '#1 ') !== false
    && strpos($decoded, '#2 ') < strpos($decoded, '#1 ') && strpos($decoded, 'MOCK-ANSWER') !== false && strpos($decoded, 'Dry run') !== false
    && strpos($decoded, 'index.php?option=com_kunena&view=topic') !== false;
$ok = $ok && $lg2 && strpos($lg2[0]['message'], 'E-mail sent to 1.') !== false;
result('bk-mail', $ok, substr($decoded ?: $mail, 0, 600) . ' | ' . json_encode($lg2));

// 6. Normal mode: member questions
$configure(array('dry_run' => 0));

// An administrator's new thread is ignored (the staff is handling it)
$t3 = newTopic($fe, $cid, 'Staff announcement thread', 'This is a staff thread that Baldrick must leave alone completely.');
result('bk-staff-ignored', !$queueOf($t3), json_encode($queueOf($t3)));

// Answerable, needs_info in Greek (with English translation), too broad, not a question, spam
$t4 = newTopic($mc, $cid, 'Frobnicator widget question', 'How do I configure the frobnicator widget to show more items?');
$t5 = newTopic($mc, $cid, 'Βοήθεια με το widget', 'Δεν δουλεύει το widget μου GREEKTEST INFOTEST, τι να κάνω;');
$t6 = newTopic($mc, $cid, 'Websites in general', 'How do I build a website? BROADTEST tell me everything');
$t7 = newTopic($mc, $cid, 'Thanks everyone', 'Just wanted to say thanks for the great forum CHATTEST');
$t8 = newTopic($mc, $cid, 'Cheap watches', 'Buy cheap watches now SPAMTEST call +1 555 0100 www.spam.example');

// Processed from the administrator page this time
$admin->get('administrator/' . kurl('view=baldrick'));
list($ok, $note) = check($admin, $step, 'bk-admin-before', 'Process the queue now');
result('bk-admin-queue', $ok, $note);
$at = JClient::token($admin->last);
$admin->post('administrator/' . kurl('view=baldrick'), array('task' => 'process', $at => 1));
save('bk-admin-after', $admin->last);

$th4 = $threadOf($t4);
$th5 = $threadOf($t5);
$th6 = $threadOf($t6);
$th7 = $threadOf($t7);
$th8 = $threadOf($t8);
$l7  = $logOf($t7);
$l8  = $logOf($t8);
result('bk-answer', count($th4['baldrick']) === 1 && strpos($th4['baldrick'][0]['message'], 'MOCK-ANSWER') !== false, json_encode($th4));
$m5 = isset($th5['baldrick'][0]['message']) ? $th5['baldrick'][0]['message'] : '';
result('bk-needs-info', strpos($m5, 'MISSING-NATIVE') !== false && strpos($m5, '[b]English translation:[/b]') !== false && strpos($m5, 'MISSING-EN') !== false, json_encode($th5));
$m6 = isset($th6['baldrick'][0]['message']) ? $th6['baldrick'][0]['message'] : '';
result('bk-canned', strpos($m6, 'index.php?option=com_kunena&view=search&query=') !== false && preg_match('/^(Hi|Hello) asker,/i', $m6) && strpos($m6, 'MOCK-ANSWER') === false, json_encode($th6));
result('bk-not-question', !$th7['baldrick'] && $l7 && $l7[0]['action'] === 'skipped' && $l7[0]['verdict'] === 'not_a_question', json_encode(array($th7, $l7)));
result('bk-spam-thread', $th8['hold'] === 1 && !$th8['baldrick'] && $l8[0]['action'] === 'spam_unpublished', json_encode(array($th8, $l8)));
$fe2 = new JClient($base, "$out/$db-guest.jar");
$fe2->get(kurl("view=topic&catid=$cid&id=$t8"));
result('bk-spam-hidden', strpos($fe2->last, 'SPAMTEST') === false, "HTTP {$fe2->code}");

clearstatcache();
$mail = is_file($mailLog) ? file_get_contents($mailLog) : '';
result('bk-mail-spam', strpos($mail, 'Baldrick unpublished spam: Cheap watches') !== false && strpos($mail, 'Baldrick sent a canned reply: Websites in general') !== false && strpos($mail, 'Thanks everyone') === false, '');

// 7. Revived threads: a reply after 7 days of silence is handled, a reply to an active thread is not; spam in a reply
//    unpublishes only the reply
$old = kexecJson('$db = JFactory::getDbo(); $t = KunenaForumTopicHelper::get(' . (int) $t7 . ', true); $old = time() - 10 * 86400; $db->setQuery("UPDATE #__kunena_messages SET time = " . $old . " WHERE thread = ' . (int) $t7 . '")->execute(); $db->setQuery("UPDATE #__kunena_topics SET first_post_time = " . $old . ", last_post_time = " . $old . " WHERE id = ' . (int) $t7 . '")->execute(); return json_encode("ok");');
$reply = function ($c, $catid, $tid, $text) {
    $html = $c->get(kurl("view=topic&layout=reply&catid=$catid&id=$tid"));
    $h = JClient::hidden($html, 'id="postform"');
    $t = JClient::token($html);
    $c->post(kurl('view=topic'), array_merge($h, array('task' => 'post', 'catid' => $catid, 'id' => $tid, 'parentid' => isset($h['parentid']) ? $h['parentid'] : 0, 'subject' => 'Re: x', 'message' => $text, $t => 1)));
};
$reply($mc, $cid, $t7, 'Coming back to this after a while: how do I update the frobnicator widget safely?');
$q7 = $queueOf($t7);
result('bk-revived-queued', $q7 && $q7[0]['status'] === 'pending' && $q7[0]['reason'] === 'revived', json_encode($q7));
$reply($mc, $cid, $t4, 'Thanks, and one more question about the frobnicator widget colours?');
$q4 = $queueOf($t4);
result('bk-active-not-queued', count($q4) === 1 && $q4[0]['status'] === 'done', json_encode($q4));

// A staff reply before processing: Baldrick stays out
$t9 = newTopic($mc, $cid, 'Question answered by staff', 'How do I reset the frobnicator widget to its defaults?');
$reply($fe, $cid, $t9, 'Staff here: use the Reset button in its settings.');
$cli('kunena:baldrick:process -n');
$th7 = $threadOf($t7);
$th9 = $threadOf($t9);
$q9  = $queueOf($t9);
result('bk-revived-answer', count($th7['baldrick']) === 1, json_encode($th7));
result('bk-staff-replied', !$th9['baldrick'] && $q9[0]['status'] === 'skipped' && strpos($q9[0]['result'], 'administrator or moderator') !== false, json_encode($q9));

// kunena:baldrick:check explains the decision; --queue (dry run here) queues a post which passes the rules
$lastOf = function ($tid) {
    return (int) kexecJson('return json_encode((int) JFactory::getDbo()->setQuery("SELECT MAX(id) FROM #__kunena_messages WHERE hold = 0 AND thread = ' . (int) $tid . ' AND userid != " . (int) KunenaBaldrick::getUserId())->loadResult());');
};
$c3 = $cli('kunena:baldrick:check ' . $lastOf($t3) . ' -n');
$c4 = $cli('kunena:baldrick:check ' . $lastOf($t4) . ' -n');

// A run which stopped without finishing leaves its thread "processing": --unlock takes it over, the activity log says so
$ts = newTopic($mc, $cid, 'Stalled frobnicator question', 'Why does the frobnicator widget stop loading after an update?');
$cs = $cli('kunena:baldrick:check ' . $lastOf($ts) . ' --queue --dry-run -n');
result('bk-cli-check', strpos($c3, 'The poster is staff') !== false && strpos($c4, 'already replied') !== false && strpos($cs, 'yes (new)') !== false && strpos($cs, 'Queue thread #' . (int) $ts) !== false, trim($c3 . "\n" . $c4 . "\n" . $cs));
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_baldrick_queue SET status = \'processing\', attempts = 3, locked_until = " . (time() + 600) . " WHERE topic_id = ' . (int) $ts . '")->execute(); return json_encode("ok");');
$s1  = $cli('kunena:baldrick:process -n');
$s2  = $cli('kunena:baldrick:process --unlock -n');
$ls  = $logOf($ts);
$ths = $threadOf($ts);
$stalledLogged = false;
foreach ($ls as $row) {
    $stalledLogged = $stalledLogged || ($row['action'] === 'error' && strpos($row['message'], 'stopped before finishing') !== false);
}
result('bk-stalled-unlock', strpos($s1, 'No thread is waiting') !== false && strpos($s1, '--unlock') !== false && count($ths['baldrick']) === 1 && $stalledLogged, trim($s1 . "\n" . $s2 . "\n" . json_encode($ls)));
// The queued post is gone (deleted, replaced): the thread's newest post is answered instead
$tf = newTopic($mc, $cid, 'Replaced frobnicator question', 'How do I move the frobnicator widget to another page?');
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_baldrick_queue SET message_id = 999999 WHERE topic_id = ' . (int) $tf . '")->execute(); return json_encode("ok");');
$cli('kunena:baldrick:process -n');
$thf = $threadOf($tf);
$qf  = $queueOf($tf);
result('bk-trigger-fallback', count($thf['baldrick']) === 1 && $qf[0]['status'] === 'done', json_encode(array($thf, $qf)));
// The member's extra posts would make them a "trusted author" for the spam checks below
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_messages SET hold = 2 WHERE thread IN (' . (int) $ts . ', ' . (int) $tf . ')")->execute(); return json_encode("ok");');

// Spam in a revived thread: only the reply is unpublished
kexecJson('$db = JFactory::getDbo(); $old = time() - 10 * 86400; $db->setQuery("UPDATE #__kunena_messages SET time = " . $old . " WHERE thread = ' . (int) $t6 . '")->execute(); $db->setQuery("UPDATE #__kunena_topics SET last_post_time = " . $old . " WHERE id = ' . (int) $t6 . '")->execute(); $db->setQuery("DELETE FROM #__kunena_messages WHERE thread = ' . (int) $t6 . ' AND userid = " . (int) KunenaBaldrick::getUserId())->execute(); KunenaForumTopicHelper::recount(' . (int) $t6 . '); return json_encode("ok");');
$reply($mc, $cid, $t6, 'Visit my casino SPAMTEST now at www.casino.example for free spins');
$cli('kunena:baldrick:process -n');
$th6 = $threadOf($t6);
$holds = array_values($th6['holds']);
result('bk-spam-reply', $th6['hold'] === 0 && end($holds) == 1 && reset($holds) == 0, json_encode(array($th6, $queueOf($t6), $logOf($t6))));

// 8. Errors: an overloaded provider is retried later, a refusal falls back to a canned reply
$configure(array('triage_model' => 'mock-overloaded'));
$t10 = newTopic($mc, $cid, 'Overloaded provider question', 'How do I export the frobnicator widget data to a file?');
$cli('kunena:baldrick:process -n');
$q10 = $queueOf($t10);
result('bk-retry', $q10 && $q10[0]['status'] === 'pending' && (int) $q10[0]['attempts'] === 1, json_encode($q10));
$configure(array('triage_model' => 'mock-haiku', 'answer_model' => 'mock-refuse', 'answer_same_connection' => 1, 'answer_max_tokens' => 4000));
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_baldrick_queue SET run_after = 0 WHERE topic_id = ' . (int) $t10 . '")->execute(); return json_encode("ok");');
$cli('kunena:baldrick:process -n');
$th10 = $threadOf($t10);
$l10  = $logOf($t10);
result('bk-refusal-canned', count($th10['baldrick']) === 1 && $l10[0]['action'] === 'canned' && stripos($l10[0]['message'], 'declined') !== false, json_encode(array($th10, $l10)));
$configure(array('answer_model' => 'mock-sonnet', 'answer_same_connection' => 0));

// Notifications "spam and errors only": no e-mail for an answer; a thread given up on is always notified
$configure(array('notify_on' => 'spam'));
$tn = newTopic($mc, $cid, 'Quiet answer question', 'How do I change the frobnicator widget title on one page only?');
$cli('kunena:baldrick:process -n');
$ln = $logOf($tn);
$te = newTopic($mc, $cid, 'Failing provider question', 'How do I reset the frobnicator widget counters every night?');
$configure(array('triage_model' => 'mock-overloaded'));
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_baldrick_queue SET attempts = 3 WHERE topic_id = ' . (int) $te . '")->execute(); return json_encode("ok");');
$cli('kunena:baldrick:process -n');
$configure(array('triage_model' => 'mock-haiku', 'notify_on' => 'all'));
$le = $logOf($te);
$qe = $queueOf($te);
clearstatcache();
$mail = is_file($mailLog) ? file_get_contents($mailLog) : '';
result('bk-notify-spam-only', $ln && $ln[0]['action'] === 'replied' && strpos($ln[0]['message'], 'E-mail') === false && strpos($mail, 'Quiet answer question') === false, json_encode($ln));
result('bk-notify-error', $qe && $qe[0]['status'] === 'failed' && $le && strpos($le[0]['message'], 'E-mail sent to 1.') !== false && strpos($mail, 'Baldrick could not answer: Failing provider question') !== false, json_encode(array($qe, $le)));
// The member's extra posts would make them a "trusted author" for later spam checks
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_messages SET hold = 2 WHERE thread IN (' . (int) $tn . ', ' . (int) $te . ') AND userid != " . (int) KunenaBaldrick::getUserId())->execute(); return json_encode("ok");');

// 9. Automatic processing: a background request to the site itself right after the page is sent
$configure(array('processing' => 'auto'));
$t11 = newTopic($mc, $cid, 'Automatic processing question', 'How do I translate the frobnicator widget labels into French?');
$done = false;
for ($i = 0; $i < 20 && !$done; $i++) {
    sleep(1);
    $q11  = $queueOf($t11);
    $done = $q11 && $q11[0]['status'] === 'done';
}
result('bk-auto', $done && count($threadOf($t11)['baldrick']) === 1, json_encode($queueOf($t11)));

// The same with PHP-FPM: processed in the same request, after the response has been sent (headers already out)
shell_exec('FCGI_SHIM=1 ' . escapeshellarg(__DIR__ . '/serve.sh') . ' start ' . escapeshellarg($db) . ' 2>&1');
sleep(1);
$t11b = newTopic($mc, $cid, 'PHP-FPM processing question', 'How do I export the frobnicator widget settings to another site?');
$done = false;
for ($i = 0; $i < 20 && !$done; $i++) {
    sleep(1);
    $q11b = $queueOf($t11b);
    $done = $q11b && $q11b[0]['status'] !== 'pending' && $q11b[0]['status'] !== 'processing';
}
result('bk-auto-fpm', $done && $q11b[0]['status'] === 'done' && count($threadOf($t11b)['baldrick']) === 1, json_encode($queueOf($t11b)));
shell_exec(escapeshellarg(__DIR__ . '/serve.sh') . ' start ' . escapeshellarg($db) . ' 2>&1');
sleep(1);
$configure(array('processing' => 'cron'));

// 10. Long model lists (OpenRouter: 300 models with prices), kept for an hour; slow, rate-limited and refusing providers
$r = $ajax('models', array('stage' => 'answer', 'provider' => 'openai', 'base_url' => $mockUrl . 'openrouter/api/v1', 'api_key' => 'or-key', 'refresh' => 1));
$chat = array_filter(isset($r['models']) ? $r['models'] : array(), function ($m) {
    return $m['chat'];
});
$m1 = isset($r['models'][0]) ? $r['models'][0] : array();
result('bk-models-long', !empty($r['success']) && count($r['models']) === 300 && count($chat) === 294 && isset($m1['price_in'], $m1['price_out'], $m1['context']) && strpos($r['message'], '300 models found') !== false, json_encode(array($r['message'], $m1)));
$r = $ajax('models', array('stage' => 'answer', 'provider' => 'openai', 'base_url' => $mockUrl . 'openrouter/api/v1', 'api_key' => 'or-key'));
$listCalls = count(array_filter(file($mockLog), function ($l) {
    return strpos($l, '"\/openrouter\/api\/v1\/models"') !== false;
}));
result('bk-models-cached', !empty($r['success']) && count($r['models']) === 300 && strpos($r['message'], 'minutes ago') !== false && $listCalls === 1, $r['message'] . " calls=$listCalls");

// The provider asks to wait 10 minutes: the thread waits that long
$configure(array('triage_model' => 'mock-ratelimit'));
$t12 = newTopic($mc, $cid, 'Rate limited question', 'How do I limit the frobnicator widget to registered users only?');
$cli('kunena:baldrick:process -n');
$q12 = kexecJson('return json_encode(JFactory::getDbo()->setQuery("SELECT status, attempts, run_after - " . time() . " AS wait FROM #__kunena_baldrick_queue WHERE topic_id = ' . (int) $t12 . '")->loadAssoc());');
result('bk-retry-after', $q12 && $q12['status'] === 'pending' && (int) $q12['wait'] >= 590 && (int) $q12['wait'] <= 610, json_encode($q12));
kexecJson('JFactory::getDbo()->setQuery("DELETE FROM #__kunena_baldrick_queue WHERE topic_id = ' . (int) $t12 . '")->execute(); return json_encode("ok");');

// A provider slower than the response timeout: retried later
$configure(array('triage_model' => 'mock-slow', 'triage_timeout' => 1));
$t13 = newTopic($mc, $cid, 'Slow provider question', 'How do I move the frobnicator widget to another module position?');
$start = time();
$cli('kunena:baldrick:process -n');
$q13 = $queueOf($t13);
result('bk-timeout', $q13 && $q13[0]['status'] === 'pending' && (int) $q13[0]['attempts'] === 1 && time() - $start < 40, json_encode($q13) . ' ' . (time() - $start) . 's');
kexecJson('JFactory::getDbo()->setQuery("DELETE FROM #__kunena_baldrick_queue WHERE topic_id = ' . (int) $t13 . '")->execute(); return json_encode("ok");');

// No credits left: Baldrick pauses, keeps the queue, says so once (notice, e-mail), resumes when the provider answers
$configure(array('triage_model' => 'mock-nocredit', 'triage_timeout' => 30, 'pause_minutes' => 15));
clearstatcache();
$mailBefore = is_file($mailLog) ? substr_count(file_get_contents($mailLog), 'Baldrick AI is paused') : 0;
$t14 = newTopic($mc, $cid, 'No credits question', 'How do I change the frobnicator widget title on mobile devices?');
$t15 = newTopic($mc, $cid, 'Second question while paused', 'How do I hide the frobnicator widget on the home page only?');
$cli('kunena:baldrick:process -n');
$pause = kexecJson('return json_encode(KunenaBaldrick::getPause());');
$q14 = $queueOf($t14);
$q15 = $queueOf($t15);
result('bk-paused', $pause && stripos($pause['reason'], 'credit balance') !== false && $pause['until'] > time() + 800 && $q14[0]['status'] === 'pending' && (int) $q14[0]['attempts'] === 0 && $q15[0]['status'] === 'pending', json_encode(array($pause, $q14, $q15)));
$cli('kunena:baldrick:process -n');
$requests = count(file($mockLog));
$cli('kunena:baldrick:process -n');
result('bk-paused-idle', count(file($mockLog)) === $requests, '');
$admin->get('administrator/' . kurl('view=cpanel'));
list($ok, $note) = check($admin, $step, 'bk-paused-notice', 'Baldrick AI is paused');
result('bk-paused-notice', $ok, $note);
clearstatcache();
$mail = file_get_contents($mailLog);
result('bk-paused-mail', substr_count($mail, 'Baldrick AI is paused') === $mailBefore + 1, '');
// The pause runs out, the provider answers again: both threads are answered, the pause ends, no second e-mail
$configure(array('triage_model' => 'mock-haiku', 'pause_until' => time() - 1));
$cli('kunena:baldrick:process -n');
$pause = kexecJson('return json_encode(KunenaBaldrick::getPause());');
clearstatcache();
result('bk-resumed', !$pause && count($threadOf($t14)['baldrick']) === 1 && count($threadOf($t15)['baldrick']) === 1 && substr_count(file_get_contents($mailLog), 'Baldrick AI is paused') === $mailBefore + 1, json_encode($pause));

// Threads waiting longer than the maximum (an outage) are skipped
$t16 = newTopic($mc, $cid, 'Very old queued question', 'How do I print the frobnicator widget results nicely?');
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_baldrick_queue SET created = " . (time() - 30 * 3600) . " WHERE topic_id = ' . (int) $t16 . '")->execute(); return json_encode("ok");');
$cli('kunena:baldrick:process -n');
$q16 = $queueOf($t16);
result('bk-max-wait', $q16[0]['status'] === 'skipped' && strpos($q16[0]['result'], 'waited longer than 24 hours') !== false && !$threadOf($t16)['baldrick'], json_encode($q16));

// Dry run switched on after a member's thread was queued: skipped when processed
$t17 = newTopic($mc, $cid, 'Queued before the dry run', 'How do I make the frobnicator widget smaller on phones?');
$configure(array('dry_run' => 1));
$cli('kunena:baldrick:process -n');
$q17 = $queueOf($t17);
result('bk-dryrun-recheck', $q17 && $q17[0]['status'] === 'skipped' && strpos($q17[0]['result'], 'Dry run') !== false && !$threadOf($t17)['baldrick'], json_encode($q17));
$configure(array('dry_run' => 0));

// Failed threads: "Retry" puts them back
kexecJson('JFactory::getDbo()->setQuery("UPDATE #__kunena_baldrick_queue SET status = \'failed\', attempts = 3 WHERE topic_id = ' . (int) $t17 . '")->execute(); return json_encode("ok");');
$admin->get('administrator/' . kurl('view=baldrick'));
$ok = strpos($admin->last, 'kunena-baldrick-retryfailed') !== false;
$at = JClient::token($admin->last);
$admin->post('administrator/' . kurl('view=baldrick'), array('task' => 'retryfailed', $at => 1));
$q17 = $queueOf($t17);
result('bk-retry-failed', $ok && $q17[0]['status'] === 'pending' && (int) $q17[0]['attempts'] === 0, json_encode($q17));
kexecJson('JFactory::getDbo()->setQuery("DELETE FROM #__kunena_baldrick_queue WHERE topic_id = ' . (int) $t17 . '")->execute(); return json_encode("ok");');

// Sample Data (posts created by code) never reaches the queue
$before = kexecJson('return json_encode((int) JFactory::getDbo()->setQuery("SELECT COUNT(*) FROM #__kunena_baldrick_queue")->loadResult());');
$sd = kexecJson('$state = array(); $r = KunenaSampledata::install(0, $state); return json_encode(KunenaSampledata::isInstalled());');
$after = kexecJson('return json_encode((int) JFactory::getDbo()->setQuery("SELECT COUNT(*) FROM #__kunena_baldrick_queue")->loadResult());');
kexecJson('$state = array(); KunenaSampledata::remove(0, $state); return json_encode("ok");');
result('bk-sampledata-ignored', $sd === true && $before === $after, json_encode(array($sd, $before, $after)));

// A conversation: the thread's starter replies to Baldrick, Baldrick answers, up to the replies per thread (3)
$configure(array('replies_per_topic' => 3));
$t18 = newTopic($mc, $cid, 'Conversation question', 'How do I install the frobnicator widget on a new site?');
$cli('kunena:baldrick:process -n');
$reply($mc, $cid, $t18, 'Thanks! And how do I configure the frobnicator widget after installing it?');
$q18 = $queueOf($t18);
$followupQueued = $q18 && $q18[0]['status'] === 'pending' && $q18[0]['reason'] === 'followup';
$cli('kunena:baldrick:process -n');
$reply($mc, $cid, $t18, 'One more: how do I update the frobnicator widget later on?');
$cli('kunena:baldrick:process -n');
$reply($mc, $cid, $t18, 'And a fourth question about the frobnicator widget backups please?');
$q18b = $queueOf($t18);
$th18 = $threadOf($t18);
result('bk-followup', $followupQueued && count($th18['baldrick']) === 3 && $q18b[0]['status'] === 'done', json_encode(array($followupQueued, count($th18['baldrick']), $q18b)));
$configure(array('replies_per_topic' => 1));

// Large forums: the full-text index (MySQL/MariaDB, PostgreSQL), created from the administrator page
$status = kexecJson('return json_encode(KunenaBaldrickRetriever::indexStatus());');
$admin->get('administrator/' . kurl('view=baldrick'));
$button = strpos($admin->last, 'kunena-baldrick-createindex') !== false;
if ($db === 'sqlite') {
    result('bk-index', $status === 'unsupported' && !$button, json_encode($status));
} else {
    $at = JClient::token($admin->last);
    $admin->post('administrator/' . kurl('view=baldrick'), array('task' => 'createindex', $at => 1));
    save('bk-index', $admin->last);
    $after = kexecJson('return json_encode(KunenaBaldrickRetriever::indexStatus());');
    $t19 = newTopic($mc, $cid, 'Frobnicator widget layout', 'How do I change the frobnicator widget layout on my site?');
    $cli('kunena:baldrick:process -n');
    $l19 = $logOf($t19);
    $m19 = isset($threadOf($t19)['baldrick'][0]['message']) ? $threadOf($t19)['baldrick'][0]['message'] : '';
    result('bk-index', $status === 'missing' && $button && $after === 'present' && $l19 && strpos($l19[0]['message'], 'full-text search') !== false && strpos($m19, 'RELATED-FOUND') !== false, json_encode(array($status, $button, $after, $l19 ? $l19[0]['message'] : null)));
}

// 11. The administrator page: totals, months, models, log
$admin->get('administrator/' . kurl('view=baldrick'));
list($ok, $note) = check($admin, $step, 'bk-admin', 'kunena-baldrick-totals');
$h  = $admin->last;
$ok = $ok && preg_match('#data-baldrick="replies">(\d+)<#', $h, $m1) && (int) $m1[1] >= 4 && preg_match('#data-baldrick="cost">\$([\d.]+)<#', $h, $m2) && (float) $m2[1] > 0
    && strpos($h, gmdate('Y-m')) !== false && strpos($h, 'mock-haiku') !== false && strpos($h, 'mock-sonnet') !== false && strpos($h, 'Spam unpublished') !== false;
result('bk-admin-page', $ok, $note);

// 12. Library checks: the request features sent to the official APIs, degrading on HTTP 400, costs, sanitising, e-mail order
$x = kexecJson(str_replace(
    'TID',
    (string) (int) $t2,
    <<<'PHP'
$sent = array();
$fail = array();
KunenaBaldrickClient::$transport = function ($method, $url, $headers, $body, $timeout) use (&$sent, &$fail) {
    $b = json_decode((string) $body, true);
    $sent[] = array('url' => $url, 'headers' => $headers, 'body' => $b);
    foreach ($fail as $word) {
        if (strpos((string) $body, $word) !== false) {
            return array(400, array(), json_encode(array('type' => 'error', 'error' => array('type' => 'invalid_request_error', 'message' => "$word: unsupported"))));
        }
    }
    if (strpos($url, 'anthropic') !== false) {
        return array(200, array(), json_encode(array('model' => $b['model'], 'content' => array(array('type' => 'text', 'text' => '{"verdict":"spam","confidence":0.9}')), 'stop_reason' => 'end_turn', 'usage' => array('input_tokens' => 1000000, 'output_tokens' => 1000000, 'cache_read_input_tokens' => 0, 'cache_creation_input_tokens' => 0))));
    }
    return array(200, array(), json_encode(array('model' => $b['model'], 'choices' => array(array('message' => array('content' => '{"ok":1}'), 'finish_reason' => 'stop')), 'usage' => array('prompt_tokens' => 10, 'completion_tokens' => 2, 'cost' => 0.5))));
};
$r = array();
$c = new KunenaBaldrickClient(array('provider' => 'anthropic', 'api_key' => 'k', 'model' => 'claude-haiku-5-5'));
$res = $c->complete('sys', 'user', KunenaBaldrickPrompt::triageSchema(), 'low');
$s = end($sent);
$r['haiku'] = array($s['url'] === 'https://api.anthropic.com/v1/messages', $s['headers']['anthropic-version'] === '2026-10-21', $s['body']['system'][0]['cache_control']['type'] === 'ephemeral', $s['body']['output_config']['format']['type'] === 'json_schema', $s['body']['output_config']['effort'] === 'low', !isset($s['body']['fallbacks']), $res['data']['verdict'] === 'spam', $res['cost'] === 600000);
$c = new KunenaBaldrickClient(array('provider' => 'anthropic', 'api_key' => 'k', 'model' => 'claude-sonnet-5-5'));
$c->complete('sys', 'user');
$s = end($sent);
$r['sonnet'] = array($s['body']['fallbacks'] === 'default', $s['headers']['anthropic-beta'] === 'server-side-fallback-2026-07-01', !isset($s['body']['output_config']));
$fail = array('effort');
$c = new KunenaBaldrickClient(array('provider' => 'anthropic', 'api_key' => 'k', 'model' => 'claude-opus-5-5'));
$c->complete('sys', 'user', null, 'low');
$s = end($sent);
$r['degrade'] = array(!isset($s['body']['output_config']['effort']), count($sent) === 4);
$fail = array();
$c = new KunenaBaldrickClient(array('provider' => 'openai', 'api_key' => 'k', 'model' => 'gpt-x'));
$res = $c->complete('sys', 'user', array('type' => 'object'));
$s = end($sent);
$r['openai'] = array($s['url'] === 'https://api.openai.com/v1/chat/completions', isset($s['body']['max_completion_tokens']), $s['body']['response_format']['type'] === 'json_object', $s['headers']['Authorization'] === 'Bearer k', $res['cost'] === 500000);
$fail = array('max_tokens', 'response_format');
$c = new KunenaBaldrickClient(array('provider' => 'openai', 'base_url' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/', 'api_key' => 'k', 'model' => 'qwen-plus'));
try { $c->complete('sys', 'user', array('type' => 'object')); } catch (Exception $e) { }
$s = end($sent);
$r['qwen'] = array($s['url'] === 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions');
$fail = array();
$c = new KunenaBaldrickClient(array('provider' => 'anthropic', 'base_url' => 'https://token-plan.ap-southeast-1.maas.aliyuncs.com/apps/anthropic', 'api_key' => 'k', 'model' => 'qwen3-max'));
$c->complete('sys', 'user', array('type' => 'object'), 'low');
$s = end($sent);
$r['qwen-anthropic'] = array($s['url'] === 'https://token-plan.ap-southeast-1.maas.aliyuncs.com/apps/anthropic/v1/messages', $s['headers']['anthropic-version'] === '2023-06-01', !isset($s['body']['output_config']), is_string($s['body']['system']));
KunenaBaldrickClient::$transport = null;
$r['json'] = array(KunenaBaldrickClient::decodeJson("Sure!\n```json\n{\"a\":1}\n```") === array('a' => 1), KunenaBaldrickClient::decodeJson('nope') === null);
$job = (object) array('site_url' => 'https://forum.example.com/sub/');
$t = KunenaBaldrickEngine::sanitise("See [url=https://forum.example.com/sub/index.php?option=com_kunena&view=topic&id=5]this[/url] and [url=https://other.example/x]that[/url] [img]http://x/y.png[/img] [iframe]x[/iframe] <b>html</b>", $job);
$r['sanitise'] = array(strpos($t, '[url=index.php?option=com_kunena&view=topic&id=5]') !== false, strpos($t, '[url=https://other.example/x]') !== false, strpos($t, '[img]') === false, strpos($t, '[iframe]') === false, strpos($t, '<b>') === false);
$posts = array();
for ($i = 1; $i <= 8; $i++) { $posts[] = array('id' => $i); }
$r['mailorder'] = array(implode(',', array_map(function ($p) { return $p['number']; }, KunenaBaldrickMailer::selectPosts($posts))) === '8,7,6,5,4,1');
$r['plain'] = array(KunenaBaldrick::plainText("[b]Hi[/b] [url=http://a.example]link[/url] [confidential]secret[/confidential][code]x = 1[/code]") === 'Hi link (http://a.example) x = 1');
$r['trigger-limit'] = array(KunenaBaldrick::countReplies(TID) === 1);
// Posts keep the ids of deleted accounts: no "Unable to load user" warning, and they are not staff
$queued = count(JFactory::getApplication()->getMessageQueue());
$r['deleted-user'] = array(KunenaBaldrick::isStaff(987654) === false, KunenaBaldrick::userExists(987654) === false, count(JFactory::getApplication()->getMessageQueue()) === $queued);
// The old "Signature" setting: moved to the account's forum signature, removed from the replies, setting dropped
$bid  = KunenaBaldrick::getUserId();
$db   = JFactory::getDbo();
$mid  = (int) $db->setQuery('SELECT MIN(id) FROM #__kunena_messages WHERE thread = ' . TID . ' AND userid = ' . $bid)->loadResult();
$orig = rtrim((string) $db->setQuery('SELECT message FROM #__kunena_messages_text WHERE mesid = ' . $mid)->loadResult());
$db->setQuery('UPDATE #__kunena_messages_text SET message = ' . $db->quote($orig . "\n\n[i]Old (text)[/i]") . ' WHERE mesid = ' . $mid)->execute();
KunenaBaldrick::saveParams(array('signature' => 'Old [text]'));
KunenaBaldrick::setParams(null);
KunenaBaldrick::setSignature($bid, '');
KunenaBaldrick::migrateSignature();
KunenaBaldrick::setParams(null);
$after = rtrim((string) $db->setQuery('SELECT message FROM #__kunena_messages_text WHERE mesid = ' . $mid)->loadResult());
$r['signature-migration'] = array($mid > 0, $after === $orig, KunenaUserHelper::get($bid, true)->signature === '[i]Old [text][/i]', !KunenaBaldrick::getParams()->exists('signature'));
$flat = array();
foreach ($r as $k => $v) { if (in_array(false, $v, true)) { $flat[] = $k . ':' . json_encode($v); } }
return json_encode($flat ? $flat : 'ok');
PHP
));
result('bk-library', $x === 'ok', json_encode($x));

shell_exec('pkill -f ' . escapeshellarg("php -S 127.0.0.1:$mockPort") . ' 2>/dev/null');
foreach (array('mailer=mail', 'mailonline=1') as $setting) {
    shell_exec('cd ' . escapeshellarg($site) . ' && php cli/joomla.php config:set ' . escapeshellarg($setting) . ' -n -q 2>&1');
}

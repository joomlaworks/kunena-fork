<?php

// Mock AI provider for Baldrick's tests (php -S 127.0.0.1:<port> baldrick-mock.php), never contacted by real sites.
//   /anthropic/v1/models, /anthropic/v1/messages               Anthropic Messages API
//   /openai/v1/models, /openai/v1/chat/completions             OpenAI-compatible Chat Completions
//   /strict/...                                               same, but rejects response_format / enable_thinking missing
// The answer depends on markers in the thread's latest post: SPAMTEST, BROADTEST, INFOTEST, CHATTEST, GREEKTEST.
// Models named "mock-overloaded" answer 529 (Anthropic) / 503; "mock-refuse" answers with stop_reason refusal.
// Every request is appended to the file named by BALDRICK_MOCK_LOG (one JSON object per line).

$path    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$raw     = file_get_contents('php://input');
$body    = json_decode($raw, true);
$headers = function_exists('getallheaders') ? getallheaders() : array();
$log     = getenv('BALDRICK_MOCK_LOG');

if ($log) {
    file_put_contents($log, json_encode(array('path' => $path, 'method' => $_SERVER['REQUEST_METHOD'], 'headers' => array_change_key_case($headers, CASE_LOWER), 'body' => $body)) . "\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json');

function out($code, $data)
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$anthropic = strpos($path, '/anthropic/') !== false;
$auth      = array_change_key_case($headers, CASE_LOWER);

if ($anthropic ? empty($auth['x-api-key']) : empty($auth['authorization'])) {
    out(401, array('error' => array('type' => 'authentication_error', 'message' => 'missing key')));
}

// OpenRouter-style list: 300 models with prices, context and output modalities (some image-only)
if (strpos($path, '/openrouter/') !== false && substr($path, -7) === '/models') {
    $data = array();

    for ($i = 1; $i <= 300; $i++) {
        $image  = $i % 50 === 0;
        $data[] = array(
            'id'             => sprintf('vendor%d/model-%03d', $i % 7, $i),
            'name'           => sprintf('Vendor %d: Model %03d', $i % 7, $i),
            'context_length' => 32000 + $i * 1000,
            'pricing'        => array('prompt' => sprintf('%.9f', $i / 10000000), 'completion' => sprintf('%.9f', $i / 2000000)),
            'architecture'   => array('output_modalities' => $image ? array('image') : array('text')),
        );
    }

    out(200, array('data' => $data));
}

// Anthropic-compatible endpoints without a model list of their own (Xiaomi MiMo): /mimo/anthropic/v1/messages works,
// the list is the OpenAI-compatible /mimo/v1/models
if (strpos($path, '/mimo/anthropic/') !== false && substr($path, -7) === '/models') {
    header('Content-Type: text/html');
    http_response_code(404);
    echo '<html><body><h1>404 Not Found</h1>openresty</body></html>';
    exit;
}

if (substr($path, -7) === '/models') {
    $models = array('mock-haiku', 'mock-sonnet', 'mock-opus');
    $data   = array();

    foreach ($models as $id) {
        $data[] = $anthropic ? array('type' => 'model', 'id' => $id, 'display_name' => ucwords(str_replace('-', ' ', $id))) : array('id' => $id, 'object' => 'model', 'owned_by' => 'mock');
    }

    out(200, $anthropic ? array('data' => $data, 'has_more' => false, 'last_id' => 'mock-opus') : array('object' => 'list', 'data' => $data));
}

if (!is_array($body)) {
    out(400, array('error' => array('message' => 'invalid JSON')));
}

if (strpos($path, '/strict/') !== false) {
    if (isset($body['response_format'])) {
        out(400, array('error' => array('message' => "'response_format' is not supported by this model")));
    }

    if (!isset($body['enable_thinking'])) {
        out(400, array('error' => array('message' => 'parameter.enable_thinking must be set to false for non-streaming calls')));
    }
}

$model = isset($body['model']) ? $body['model'] : '';

if ($model === 'mock-nocredit') {
    if ($anthropic) {
        out(400, array('type' => 'error', 'error' => array('type' => 'invalid_request_error', 'message' => 'Your credit balance is too low to access the Anthropic API. Please go to Plans & Billing to upgrade or purchase credits.')));
    }

    out(402, array('error' => array('code' => 402, 'message' => 'Insufficient credits. Add more using https://openrouter.ai/settings/credits')));
}

if ($model === 'mock-ratelimit') {
    header('Retry-After: 600');
    out(429, array('error' => array('type' => 'rate_limit_error', 'message' => 'Rate limited')));
}

if ($model === 'mock-slow') {
    sleep(3);
}

if ($model === 'mock-overloaded') {
    out($anthropic ? 529 : 503, array('error' => array('type' => 'overloaded_error', 'message' => 'Overloaded')));
}

// The prompt
$system = '';
$user   = '';

if ($anthropic) {
    $system = is_array($body['system']) ? $body['system'][0]['text'] : (string) $body['system'];
    $user   = (string) $body['messages'][0]['content'];
} else {
    foreach ($body['messages'] as $m) {
        if ($m['role'] === 'system') {
            $system = $m['content'];
        } else {
            $user = $m['content'];
        }
    }
}

$triage = strpos($system, 'triage step') !== false;
preg_match_all('#<post number="\d+"[^>]*>(.*?)</post>#s', $user, $posts);
$last    = $posts[1] ? end($posts[1]) : $user;
$subject = preg_match('#<thread subject="([^"]*)"#', $user, $m) ? $m[1] : '';

if ($triage) {
    $verdict = 'answerable';

    foreach (array('SPAMTEST' => 'spam', 'BROADTEST' => 'too_broad', 'INFOTEST' => 'needs_info', 'CHATTEST' => 'not_a_question') as $marker => $v) {
        if (strpos($last, $marker) !== false) {
            $verdict = $v;
        }
    }

    $greek = strpos($last, 'GREEKTEST') !== false;
    $text  = json_encode(array(
        'verdict'    => $verdict,
        'confidence' => strpos($last, 'UNSURE') !== false ? 0.5 : 0.97,
        'language'   => $greek ? 'Greek' : 'English',
        'question'   => $verdict === 'answerable' ? 'How to fix: ' . $subject : '',
        'queries'    => array($subject, 'frobnicator setup'),
        'missing'    => $verdict === 'needs_info' ? ($greek ? 'Παρακαλώ πείτε μας την έκδοση. MISSING-NATIVE' : 'Please tell us your version. MISSING-NATIVE') : '',
        'missing_en' => $verdict === 'needs_info' && $greek ? 'Please tell us your version. MISSING-EN' : '',
        'reason'     => 'mock verdict ' . $verdict,
    ));
    // Some models wrap JSON in a code fence
    $text = $anthropic ? $text : "```json\n" . $text . "\n```";
} elseif ($model === 'mock-refuse') {
    out(200, array('id' => 'msg_x', 'type' => 'message', 'role' => 'assistant', 'model' => $model, 'content' => array(), 'stop_reason' => 'refusal', 'usage' => array('input_tokens' => 10, 'output_tokens' => 0)));
} else {
    // The answer: cites the first related thread, links the site absolutely, tries an image and HTML
    $link = preg_match('#<related_thread title="[^"]*" link="([^"]+)"#', $user, $m) ? $m[1] : '';
    $site = getenv('BALDRICK_MOCK_SITE') ?: 'http://127.0.0.1:8101/';
    $text = "Hi! Here is how to fix it. MOCK-ANSWER\n\n[list=1][*]Open the [b]settings[/b].[*]Save.[/list]\n"
        . ($link !== '' ? "See [url=$link]the related thread[/url]. RELATED-FOUND\n" : "NO-RELATED\n")
        . "Also [url={$site}index.php?option=com_kunena&view=category&layout=list]the forum[/url].\n"
        . "[img]https://evil.example/x.png[/img]<script>alert(1)</script>";

    if (strpos($user, 'Write the reply in Greek') !== false) {
        $text .= "\nΓεια σας GREEK-REPLY";
    }

    if (strpos($user, 'English translation') !== false) {
        $text .= "\n\n[b]English translation:[/b]\nHello TRANSLATED";
    }
}

$usage = array('in' => 1000 + strlen($user) % 100, 'out' => 200);

if ($anthropic) {
    out(200, array(
        'id'          => 'msg_mock',
        'type'        => 'message',
        'role'        => 'assistant',
        'model'       => $model,
        'content'     => array(array('type' => 'thinking', 'thinking' => 'hidden'), array('type' => 'text', 'text' => $text)),
        'stop_reason' => 'end_turn',
        'usage'       => array('input_tokens' => $usage['in'], 'output_tokens' => $usage['out'], 'cache_read_input_tokens' => 50, 'cache_creation_input_tokens' => 0),
    ));
}

out(200, array(
    'id'      => 'chatcmpl-mock',
    'object'  => 'chat.completion',
    'model'   => $model,
    'choices' => array(array('index' => 0, 'message' => array('role' => 'assistant', 'content' => $text), 'finish_reason' => 'stop')),
    'usage'   => array('prompt_tokens' => $usage['in'], 'completion_tokens' => $usage['out'], 'prompt_tokens_details' => array('cached_tokens' => 100)),
));

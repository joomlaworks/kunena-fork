<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Http\HttpFactory;
use Joomla\Registry\Registry;

/**
 * Minimal client for the two API styles Baldrick speaks: Anthropic's Messages API and OpenAI-compatible Chat
 * Completions (OpenAI, Qwen/DashScope, OpenRouter, DeepSeek, Ollama, vLLM, ...).
 *
 * Raw HTTP on purpose: the vendors' SDKs need Composer and newer PHP versions than Kunena supports (PHP 7.1), and one
 * client has to talk to any compatible endpoint. Optional request features (structured output, effort, prompt caching,
 * server-side fallbacks, JSON mode) are sent only where they are known to work, and are dropped and retried once when
 * an endpoint rejects them with HTTP 400.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickClient
{
    /**
     * Anthropic API version sent to api.anthropic.com
     *
     * @since 5.3.0
     */
    public const ANTHROPIC_VERSION = '2026-10-21';

    /**
     * Anthropic API version sent to Anthropic-compatible endpoints of other providers (the widely implemented one)
     *
     * @since 5.3.0
     */
    public const ANTHROPIC_COMPAT_VERSION = '2023-06-01';

    /**
     * Beta header for server-side fallbacks on safety refusals
     *
     * @since 5.3.0
     */
    public const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    /**
     * USD per million input/output tokens of known models, used when no price is configured
     *
     * @var array
     * @since 5.3.0
     */
    public static $prices = array(
        'claude-opus-5-5'   => array(4.0, 20.0),
        'claude-sonnet-5-5' => array(2.0, 10.0),
        'claude-haiku-5-5'  => array(0.10, 0.50),
    );

    /**
     * @var array  provider, base_url, api_key, model, max_tokens, price_in, price_out, timeout, fallbacks
     * @since 5.3.0
     */
    protected $conn;

    /**
     * @var string  Where the model list came from, when it wasn't the endpoint's own (see openaiModelList())
     * @since 5.3.0
     */
    public $modelListUrl = '';

    /**
     * @var callable|null  Transport override (tests): function ($method, $url, $headers, $body, $timeout) returning
     *                     array(status, headers, body)
     * @since 5.3.0
     */
    public static $transport = null;

    /**
     * @param   array  $conn  Connection settings
     *
     * @since 5.3.0
     */
    public function __construct(array $conn)
    {
        $conn += array(
            'provider'   => 'anthropic',
            'base_url'   => '',
            'api_key'    => '',
            'model'      => '',
            'max_tokens' => 2048,
            'price_in'   => '',
            'price_out'  => '',
            'timeout'    => 60,
            'connect'    => 10,
            'fallbacks'  => 1,
        );

        $conn['provider'] = $conn['provider'] === 'openai' ? 'openai' : 'anthropic';
        $conn['base_url'] = trim((string) $conn['base_url']);

        if ($conn['base_url'] === '') {
            $conn['base_url'] = $conn['provider'] === 'openai' ? 'https://api.openai.com/v1' : 'https://api.anthropic.com';
        }

        $conn['base_url'] = rtrim($conn['base_url'], '/');
        $conn['api_key']  = trim((string) $conn['api_key']);
        $conn['model']    = trim((string) $conn['model']);

        $this->conn = $conn;
    }

    /**
     * @return  string  The model
     *
     * @since 5.3.0
     */
    public function getModel()
    {
        return $this->conn['model'];
    }

    /**
     * @return  string  The API key
     *
     * @since 5.3.0
     */
    public function getApiKey()
    {
        return $this->conn['api_key'];
    }

    /**
     * @return  string  anthropic or openai
     *
     * @since 5.3.0
     */
    public function getProvider()
    {
        return $this->conn['provider'];
    }

    /**
     * Checks a base URL entered by an administrator.
     *
     * @param   string  $url  URL
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isValidBaseUrl($url)
    {
        $url = trim((string) $url);

        if ($url === '') {
            return true;
        }

        $parts = parse_url($url);

        return $parts !== false && !empty($parts['host']) && isset($parts['scheme'])
            && in_array(strtolower($parts['scheme']), array('http', 'https'), true)
            && empty($parts['user']) && empty($parts['pass']);
    }

    /**
     * @return  boolean  The connection talks to Anthropic's own API (where the optional features are known to work)
     *
     * @since 5.3.0
     */
    protected function isAnthropicApi()
    {
        return $this->conn['provider'] === 'anthropic' && strtolower((string) parse_url($this->conn['base_url'], PHP_URL_HOST)) === 'api.anthropic.com';
    }

    /**
     * @return  boolean
     *
     * @since 5.3.0
     */
    protected function isOpenAiApi()
    {
        return $this->conn['provider'] === 'openai' && strtolower((string) parse_url($this->conn['base_url'], PHP_URL_HOST)) === 'api.openai.com';
    }

    /**
     * @param   string  $path  Path (messages, models)
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected function url($path)
    {
        $base = $this->conn['base_url'];

        if ($this->conn['provider'] === 'anthropic') {
            // Accept the base URL with or without the version part
            return preg_replace('#/v1$#', '', $base) . '/v1/' . $path;
        }

        return $base . '/' . $path;
    }

    /**
     * @return  array  HTTP headers
     *
     * @since 5.3.0
     */
    protected function headers()
    {
        $headers = array('Content-Type' => 'application/json', 'Accept' => 'application/json');

        if ($this->conn['provider'] === 'anthropic') {
            $headers['x-api-key']         = $this->conn['api_key'];
            $headers['anthropic-version'] = $this->isAnthropicApi() ? static::ANTHROPIC_VERSION : static::ANTHROPIC_COMPAT_VERSION;
        } else {
            $headers['Authorization'] = 'Bearer ' . $this->conn['api_key'];
        }

        return $headers;
    }

    /**
     * Lists the models the endpoint offers.
     *
     * @return  array  [['id' => ..., 'name' => ...], ...] sorted by id
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    public function listModels()
    {
        $models = array();
        $after  = '';

        // Anthropic pages its list; OpenAI-compatible endpoints return everything at once
        for ($page = 0; $page < 10; $page++) {
            $url = $this->url('models');

            if ($this->conn['provider'] === 'anthropic') {
                $url .= '?limit=1000' . ($after !== '' ? '&after_id=' . rawurlencode($after) : '');
            }

            try {
                $data = $this->request('GET', $url, null);
            } catch (KunenaBaldrickException $e) {
                // Many Anthropic-compatible endpoints only offer the messages API (e.g. Xiaomi MiMo, Qwen): their model list is
                // the provider's OpenAI-compatible one
                if ($page > 0 || $this->conn['provider'] !== 'anthropic' || !in_array((int) $e->getCode(), array(404, 405), true)) {
                    throw $e;
                }

                $data = $this->openaiModelList();
            }

            $list = isset($data['data']) && is_array($data['data']) ? $data['data'] : (isset($data['models']) && is_array($data['models']) ? $data['models'] : array());

            foreach ($list as $item) {
                if (is_string($item)) {
                    $item = array('id' => $item);
                }

                if (!is_array($item) || empty($item['id']) || !is_string($item['id'])) {
                    continue;
                }

                $name  = isset($item['display_name']) && is_string($item['display_name']) ? $item['display_name'] : (isset($item['name']) && is_string($item['name']) ? $item['name'] : '');
                $model = array('id' => $item['id'], 'name' => $name, 'chat' => static::isChatModel($item));

                // OpenRouter: USD per token, and the context window
                if (isset($item['pricing']['prompt'], $item['pricing']['completion']) && is_numeric($item['pricing']['prompt']) && is_numeric($item['pricing']['completion'])) {
                    $model['price_in']  = static::perMillion($item['pricing']['prompt']);
                    $model['price_out'] = static::perMillion($item['pricing']['completion']);
                }

                if (isset($item['context_length']) && is_numeric($item['context_length'])) {
                    $model['context'] = (int) $item['context_length'];
                }

                $models[$item['id']] = $model;
            }

            if ($this->conn['provider'] !== 'anthropic' || $this->modelListUrl !== '' || empty($data['has_more']) || empty($data['last_id'])) {
                break;
            }

            $after = (string) $data['last_id'];
        }

        ksort($models, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($models);
    }

    /**
     * The OpenAI-compatible model list of a provider whose Anthropic-compatible endpoint has none, at the usual places
     * next to it: https://host/anthropic -> https://host/v1/models (Xiaomi MiMo), https://host/apps/anthropic ->
     * https://host/compatible-mode/v1/models (Qwen/DashScope).
     *
     * @return  array  Decoded list
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    protected function openaiModelList()
    {
        $base  = preg_replace('#/v1$#', '', $this->conn['base_url']);
        $roots = array();

        if (preg_match('#^(.*?)(?:/apps)?/anthropic$#i', $base, $m)) {
            $roots[] = $m[1];
        }

        $roots[] = $base;
        $urls    = array();

        foreach ($roots as $root) {
            $urls[] = $root . '/v1/models';
            $urls[] = $root . '/compatible-mode/v1/models';
        }

        foreach (array_unique($urls) as $url) {
            if ($url === $this->url('models')) {
                continue;
            }

            try {
                // Both authentication styles: these gateways accept one or the other
                $data = $this->request('GET', $url, null, array('Authorization' => 'Bearer ' . $this->conn['api_key']));
            } catch (KunenaBaldrickException $e) {
                if (in_array((int) $e->getCode(), array(404, 405), true)) {
                    continue;
                }

                throw $e;
            }

            if (isset($data['data']) && is_array($data['data'])) {
                $this->modelListUrl = $url;

                return $data;
            }
        }

        throw new KunenaBaldrickException('This endpoint does not list its models (HTTP 404). Type the model name from the provider\'s documentation, then use "Test".', 404);
    }

    /**
     * @param   array  $item  Model of a models list
     *
     * @return  boolean  The model can write chat answers (false for embedding, speech, image, moderation models...)
     *
     * @since 5.3.0
     */
    public static function isChatModel(array $item)
    {
        if (isset($item['architecture']['output_modalities']) && is_array($item['architecture']['output_modalities'])) {
            return in_array('text', $item['architecture']['output_modalities'], true);
        }

        return !preg_match('/embed|tts|whisper|dall-?e|davinci|babbage|moderation|transcri|speech|audio|realtime|image|rerank|vision-only|sora|-search-|computer-use/i', (string) $item['id']);
    }

    /**
     * @param   mixed  $perToken  USD per token
     *
     * @return  string  USD per million tokens
     *
     * @since 5.3.0
     */
    protected static function perMillion($perToken)
    {
        $value = round((float) $perToken * 1000000, 4);

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * Sends one prompt and returns the answer.
     *
     * @param   string      $system   System prompt
     * @param   string      $user     User message
     * @param   array|null  $schema   JSON schema: ask for a JSON object (structured output where supported)
     * @param   string      $effort   Effort (Anthropic API only: low, medium, high), '' for the model's default
     *
     * @return  array  text, data (decoded JSON when a schema was given), stop, model, usage (input, output, cache_read,
     *                 cache_write), cost (millionths of a USD), duration (ms)
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    public function complete($system, $user, ?array $schema = null, $effort = '')
    {
        if ($this->conn['model'] === '') {
            throw new KunenaBaldrickException('No model is configured.');
        }

        $start = microtime(true);

        // Optional features: dropped one by one when the endpoint rejects them
        $features = array();

        if ($this->conn['provider'] === 'anthropic') {
            if ($this->isAnthropicApi()) {
                $features['cache_control'] = true;

                if ($schema) {
                    $features['output_config.format'] = true;
                }

                if ($effort !== '' && preg_match('/^claude-(opus|sonnet|haiku|fable)-5/', $this->conn['model'])) {
                    $features['output_config.effort'] = true;
                }

                // Server-side fallback on safety refusals (not offered for Haiku)
                if (!empty($this->conn['fallbacks']) && preg_match('/^claude-(opus-5|sonnet-5-5|fable-5)/', $this->conn['model'])) {
                    $features['fallbacks'] = true;
                }
            }
        } else {
            if ($schema) {
                $features['response_format'] = true;
            }

            $features[$this->isOpenAiApi() ? 'max_completion_tokens' : 'max_tokens'] = true;
        }

        for ($try = 0; $try < 4; $try++) {
            list($body, $headers) = $this->conn['provider'] === 'anthropic'
                ? $this->anthropicBody($system, $user, $schema, $effort, $features)
                : $this->openaiBody($system, $user, $features);

            try {
                $data = $this->request('POST', $this->url($this->conn['provider'] === 'anthropic' ? 'messages' : 'chat/completions'), $body, $headers);
                break;
            } catch (KunenaBaldrickException $e) {
                $drop = $e->getCode() == 400 ? $this->featureToDrop($e->getMessage(), $features) : null;

                if ($drop === null) {
                    throw $e;
                }

                // OpenAI's newer models want max_completion_tokens, most compatible endpoints max_tokens
                if ($drop === 'max_tokens' || $drop === 'max_completion_tokens') {
                    unset($features[$drop]);
                    $features[$drop === 'max_tokens' ? 'max_completion_tokens' : 'max_tokens'] = true;
                } elseif ($drop === 'enable_thinking') {
                    $features['enable_thinking'] = false;
                } else {
                    unset($features[$drop]);
                }
            }
        }

        if (!isset($data)) {
            throw new KunenaBaldrickException('The provider rejected the request.');
        }

        $result = $this->conn['provider'] === 'anthropic' ? $this->anthropicResult($data) : $this->openaiResult($data);

        $result['duration'] = (int) round((microtime(true) - $start) * 1000);
        $result['cost']     = $this->cost($result['usage'], isset($result['provider_cost']) ? $result['provider_cost'] : null);

        if ($result['stop'] === 'refusal') {
            $e          = new KunenaBaldrickException('The model declined to answer (refusal).');
            $e->refusal = true;
            $e->usage   = $result;

            throw $e;
        }

        if ($schema) {
            $result['data'] = static::decodeJson($result['text']);

            if (!is_array($result['data'])) {
                $e        = new KunenaBaldrickException($result['stop'] === 'max_tokens' || $result['stop'] === 'length'
                    ? 'The answer was cut off at the maximum number of tokens: raise "Max tokens".'
                    : 'The model did not return valid JSON.');
                $e->usage = $result;

                throw $e;
            }
        }

        return $result;
    }

    /**
     * Picks the optional feature an HTTP 400 error message complains about.
     *
     * @param   string  $message   Error message
     * @param   array   $features  Enabled features
     *
     * @return  string|null
     *
     * @since 5.3.0
     */
    protected function featureToDrop($message, array $features)
    {
        $message = strtolower($message);
        $checks  = array(
            'fallbacks'             => array('fallback'),
            'output_config.effort'  => array('effort'),
            'output_config.format'  => array('output_config', 'json_schema', 'format'),
            'cache_control'         => array('cache_control'),
            'response_format'       => array('response_format', 'json_object', 'json mode'),
            'max_completion_tokens' => array('max_completion_tokens'),
            'max_tokens'            => array('max_tokens'),
        );

        // Qwen3 open-source models refuse non-streaming calls unless thinking is switched off
        if (strpos($message, 'enable_thinking') !== false && !array_key_exists('enable_thinking', $features)) {
            return 'enable_thinking';
        }

        foreach ($checks as $feature => $words) {
            if (empty($features[$feature])) {
                continue;
            }

            foreach ($words as $word) {
                if (strpos($message, $word) !== false) {
                    return $feature;
                }
            }
        }

        return null;
    }

    /**
     * @param   string      $system    System prompt
     * @param   string      $user      User message
     * @param   array|null  $schema    JSON schema
     * @param   string      $effort    Effort
     * @param   array       $features  Enabled features
     *
     * @return  array  body, extra headers
     *
     * @since 5.3.0
     */
    protected function anthropicBody($system, $user, $schema, $effort, array $features)
    {
        $body = array(
            'model'      => $this->conn['model'],
            'max_tokens' => max(64, (int) $this->conn['max_tokens']),
        );

        if (!empty($features['cache_control'])) {
            // The system prompt is the same for every thread: cache it
            $body['system'] = array(array('type' => 'text', 'text' => $system, 'cache_control' => array('type' => 'ephemeral')));
        } else {
            $body['system'] = $system;
        }

        $body['messages'] = array(array('role' => 'user', 'content' => $user));

        if (!empty($features['output_config.format'])) {
            $body['output_config']['format'] = array('type' => 'json_schema', 'schema' => $schema);
        }

        if (!empty($features['output_config.effort'])) {
            $body['output_config']['effort'] = $effort;
        }

        $headers = array();

        if (!empty($features['fallbacks'])) {
            $body['fallbacks']         = 'default';
            $headers['anthropic-beta'] = static::FALLBACK_BETA;
        }

        return array($body, $headers);
    }

    /**
     * @param   string  $system    System prompt
     * @param   string  $user      User message
     * @param   array   $features  Enabled features
     *
     * @return  array  body, extra headers
     *
     * @since 5.3.0
     */
    protected function openaiBody($system, $user, array $features)
    {
        $body = array(
            'model'    => $this->conn['model'],
            'messages' => array(
                array('role' => 'system', 'content' => $system),
                array('role' => 'user', 'content' => $user),
            ),
        );

        $max = max(64, (int) $this->conn['max_tokens']);

        if (!empty($features['max_completion_tokens'])) {
            $body['max_completion_tokens'] = $max;
        } else {
            $body['max_tokens'] = $max;
        }

        if (!empty($features['response_format'])) {
            $body['response_format'] = array('type' => 'json_object');
        }

        if (array_key_exists('enable_thinking', $features)) {
            $body['enable_thinking'] = false;
        }

        return array($body, array());
    }

    /**
     * @param   array  $data  Response
     *
     * @return  array
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    protected function anthropicResult(array $data)
    {
        if (!isset($data['content']) || !is_array($data['content'])) {
            throw new KunenaBaldrickException('Unexpected response from the provider (no content).');
        }

        $text = '';

        // Only text blocks: thinking and fallback blocks are skipped
        foreach ($data['content'] as $block) {
            if (is_array($block) && isset($block['type'], $block['text']) && $block['type'] === 'text' && is_string($block['text'])) {
                $text .= $block['text'];
            }
        }

        $usage = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : array();

        return array(
            'text'  => trim($text),
            'stop'  => isset($data['stop_reason']) ? (string) $data['stop_reason'] : '',
            'model' => isset($data['model']) && is_string($data['model']) ? $data['model'] : $this->conn['model'],
            'usage' => array(
                'input'       => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : 0,
                'output'      => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : 0,
                'cache_read'  => isset($usage['cache_read_input_tokens']) ? (int) $usage['cache_read_input_tokens'] : 0,
                'cache_write' => isset($usage['cache_creation_input_tokens']) ? (int) $usage['cache_creation_input_tokens'] : 0,
            ),
        );
    }

    /**
     * @param   array  $data  Response
     *
     * @return  array
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    protected function openaiResult(array $data)
    {
        if (empty($data['choices'][0]) || !is_array($data['choices'][0])) {
            throw new KunenaBaldrickException('Unexpected response from the provider (no choices).');
        }

        $choice  = $data['choices'][0];
        $content = isset($choice['message']['content']) ? $choice['message']['content'] : '';

        if (is_array($content)) {
            $parts = '';

            foreach ($content as $part) {
                if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                    $parts .= $part['text'];
                }
            }

            $content = $parts;
        }

        // Reasoning some models write inline
        $content = preg_replace('#<think>.*?</think>#s', '', (string) $content);

        $usage  = isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : array();
        $prompt = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0;
        $cached = isset($usage['prompt_tokens_details']['cached_tokens']) ? (int) $usage['prompt_tokens_details']['cached_tokens'] : 0;
        $stop   = isset($choice['finish_reason']) ? (string) $choice['finish_reason'] : '';

        $result = array(
            'text'  => trim($content),
            'stop'  => $stop === 'length' ? 'max_tokens' : $stop,
            'model' => isset($data['model']) && is_string($data['model']) ? $data['model'] : $this->conn['model'],
            'usage' => array(
                'input'       => max(0, $prompt - $cached),
                'output'      => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0,
                'cache_read'  => $cached,
                'cache_write' => 0,
            ),
        );

        // Some gateways (OpenRouter) report the cost themselves
        if (isset($usage['cost']) && is_numeric($usage['cost'])) {
            $result['provider_cost'] = (float) $usage['cost'];
        }

        return $result;
    }

    /**
     * Estimated cost of a request in millionths of a USD.
     *
     * @param   array       $usage         Token usage
     * @param   float|null  $providerCost  Cost reported by the provider (USD)
     *
     * @return  integer
     *
     * @since 5.3.0
     */
    public function cost(array $usage, $providerCost = null)
    {
        if ($providerCost !== null) {
            return (int) round($providerCost * 1000000);
        }

        $in  = trim((string) $this->conn['price_in']);
        $out = trim((string) $this->conn['price_out']);

        if (($in === '' || $out === '') && isset(static::$prices[$this->conn['model']])) {
            $known = static::$prices[$this->conn['model']];
            $in    = $in === '' ? $known[0] : $in;
            $out   = $out === '' ? $known[1] : $out;
        }

        $in  = (float) str_replace(',', '.', (string) $in);
        $out = (float) str_replace(',', '.', (string) $out);

        // Prices are per million tokens, the result is in millionths of a USD: the factors cancel out.
        // Cache reads cost a tenth of the input price, cache writes a quarter more (Anthropic's rates).
        $cost = $usage['input'] * $in + $usage['output'] * $out + $usage['cache_read'] * $in * 0.1 + $usage['cache_write'] * $in * 1.25;

        return (int) round($cost);
    }

    /**
     * Decodes a JSON object from a model's answer, tolerating code fences and text around it.
     *
     * @param   string  $text  Text
     *
     * @return  array|null
     *
     * @since 5.3.0
     */
    public static function decodeJson($text)
    {
        $text = trim((string) $text);
        $data = json_decode($text, true);

        if (is_array($data)) {
            return $data;
        }

        $start = strpos($text, '{');
        $end   = strrpos($text, '}');

        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $data = json_decode(substr($text, $start, $end - $start + 1), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Sends a request, retrying rate limits and overloaded/unavailable responses.
     *
     * @param   string      $method   GET or POST
     * @param   string      $url      URL
     * @param   array|null  $body     JSON body
     * @param   array       $headers  Extra headers
     *
     * @return  array  Decoded JSON response
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    protected function request($method, $url, $body, array $headers = array())
    {
        if ($this->conn['api_key'] === '') {
            throw new KunenaBaldrickException('No API key is configured.');
        }

        if (!static::isValidBaseUrl($this->conn['base_url'])) {
            throw new KunenaBaldrickException('The base URL must be an http(s) URL.');
        }

        $headers = array_merge($this->headers(), $headers);
        $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $timeout = max(1, (int) $this->conn['timeout']);

        for ($attempt = 0; ; $attempt++) {
            list($status, $responseHeaders, $responseBody) = $this->send($method, $url, $headers, $payload, $timeout);

            $data = json_decode((string) $responseBody, true);

            if ($status >= 200 && $status < 300 && is_array($data)) {
                return $data;
            }

            $message = static::errorMessage($status, $data, $responseBody);
            $account = static::isAccountError($status, $data, $message);

            // 429 rate limited, 529 overloaded (Anthropic), 5xx unavailable, 0 no connection (not: quota exhausted)
            $retryable = !$account && ($status === 0 || $status === 408 || $status === 409 || $status === 429 || $status >= 500);
            $after     = 0;

            foreach ($responseHeaders as $name => $value) {
                $value = is_array($value) ? reset($value) : $value;

                if (strtolower($name) === 'retry-after' && is_numeric($value)) {
                    $after = max(0, (int) $value);
                }
            }

            // Short waits here; longer ones are left to the queue (the job is retried when the provider said)
            if ($retryable && $attempt < 2 && $after <= 20) {
                sleep(max(1, $after ? $after : 2 * ($attempt + 1)));
                continue;
            }

            $e             = new KunenaBaldrickException($message, $status, $retryable);
            $e->account    = $account;
            $e->retryAfter = $after;

            throw $e;
        }
    }

    /**
     * Errors which every request will get until an administrator acts: invalid or expired key, no permission, no credits
     * or quota left, unknown model or endpoint.
     *
     * @param   integer  $status   HTTP status
     * @param   mixed    $data     Decoded body
     * @param   string   $message  Error message
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isAccountError($status, $data, $message)
    {
        if (in_array((int) $status, array(401, 402, 403, 404), true)) {
            return true;
        }

        $code = '';

        if (is_array($data) && isset($data['error']) && is_array($data['error'])) {
            $code = strtolower(trim((isset($data['error']['code']) && is_scalar($data['error']['code']) ? $data['error']['code'] : '')
                . ' ' . (isset($data['error']['type']) && is_scalar($data['error']['type']) ? $data['error']['type'] : '')));
        }

        // OpenAI answers 429 "insufficient_quota" when the credits are used up, Anthropic 400 "credit balance is too low"
        return ($status == 429 || $status == 400)
            && (bool) preg_match('/insufficient_quota|billing|credit balance|credits|quota exceeded|exceeded your current quota|arrearage|account.{0,20}(suspended|disabled|overdue)/i', $code . ' ' . $message);
    }

    /**
     * @param   integer     $status  HTTP status
     * @param   mixed       $data    Decoded body
     * @param   string      $raw     Raw body
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function errorMessage($status, $data, $raw)
    {
        $message = '';

        if (is_array($data)) {
            if (isset($data['error']['message']) && is_string($data['error']['message'])) {
                $message = $data['error']['message'];
            } elseif (isset($data['message']) && is_string($data['message'])) {
                $message = $data['message'];
            } elseif (isset($data['error']) && is_string($data['error'])) {
                $message = $data['error'];
            }
        }

        if ($message === '') {
            $message = $status ? 'Unexpected response: ' . substr(trim(strip_tags((string) $raw)), 0, 200) : (string) $raw;
        }

        return ($status ? 'HTTP ' . $status . ': ' : '') . $message;
    }

    /**
     * @param   string       $method   Method
     * @param   string       $url      URL
     * @param   array        $headers  Headers
     * @param   string|null  $payload  Body
     * @param   integer      $timeout  Timeout (seconds)
     *
     * @return  array  status (0 on connection errors, then the body is the error), headers, body
     *
     * @since 5.3.0
     */
    protected function send($method, $url, array $headers, $payload, $timeout)
    {
        if (static::$transport) {
            return call_user_func(static::$transport, $method, $url, $headers, $payload, $timeout);
        }

        try {
            // A short connection timeout (network problems show quickly), the full timeout for the answer to arrive
            $options = new Registry();

            if (defined('CURLOPT_CONNECTTIMEOUT')) {
                $options->set('transport.curl', array(CURLOPT_CONNECTTIMEOUT => max(1, min((int) $this->conn['connect'], $timeout))));
            }

            $http     = HttpFactory::getHttp($options, array('curl', 'stream'));
            $response = $method === 'GET' ? $http->get($url, $headers, $timeout) : $http->post($url, (string) $payload, $headers, $timeout);
        } catch (Exception $e) {
            return array(0, array(), 'Connection failed: ' . $e->getMessage());
        }

        return array((int) $response->code, (array) $response->headers, (string) $response->body);
    }
}

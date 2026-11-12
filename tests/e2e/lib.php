<?php

// Minimal HTTP client for driving a Joomla site with curl (cookie jar, form tokens).
class JClient
{
    public $base;
    public $jar;
    public $last;
    public $lastUrl;
    public $code;
    public $verbose = false;

    public function __construct($base, $jar)
    {
        $this->base = rtrim($base, '/') . '/';
        $this->jar  = $jar;
    }

    public function req($path, $post = null, $follow = true, $headers = array())
    {
        $url = preg_match('#^https?://#', $path) ? $path : $this->base . ltrim($path, '/');
        $ch  = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_TIMEOUT => 300, CURLOPT_HTTPHEADER => $headers,
        ));
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }
        $this->last    = (string) curl_exec($ch);
        $this->code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        if ($this->verbose) {
            fwrite(STDERR, ($post !== null ? 'POST ' : 'GET ') . "$url -> {$this->code} {$this->lastUrl}\n");
        }
        return $this->last;
    }

    public function get($path)
    {
        return $this->req($path);
    }
    public function post($path, $data)
    {
        return $this->req($path, $data);
    }

    public static function token($html)
    {
        if (preg_match('#<input[^>]+type="hidden"[^>]+name="([a-f0-9]{32})"[^>]+value="1"#', $html, $m)) {
            return $m[1];
        }
        if (preg_match('#name="([a-f0-9]{32})" value="1"#', $html, $m)) {
            return $m[1];
        }
        if (preg_match('#"csrf\.token":"([a-f0-9]{32})"#', $html, $m)) {
            return $m[1];
        }
        if (preg_match('#([a-f0-9]{32})=1#', $html, $m)) {
            return $m[1];
        }
        return null;
    }

    public static function hidden($html, $formMatch = null)
    {
        $out = array();
        if ($formMatch && preg_match('#' . $formMatch . '#', $html, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
            $end = strpos($html, '</form>', $start);
            $html = substr($html, $start, $end === false ? null : $end - $start);
        }
        preg_match_all('#<input[^>]*type="hidden"[^>]*>#i', $html, $all);
        foreach ($all[0] as $tag) {
            if (preg_match('#name="([^"]+)"#', $tag, $n)) {
                $v = preg_match('#value="([^"]*)"#', $tag, $vv) ? html_entity_decode($vv[1]) : '';
                $out[$n[1]] = $v;
            }
        }
        return $out;
    }

    public function adminLogin($user, $pass)
    {
        $html = $this->get('administrator/index.php');
        if (strpos($html, 'task=logout') !== false || strpos($html, 'com_login') === false && strpos($html, 'mod-login') === false && strpos($html, 'form-login') === false) {
            if (strpos($html, 'name="passwd"') === false) {
                return true;
            }
        }
        $t = self::token($html);
        $this->post('administrator/index.php', array('username' => $user, 'passwd' => $pass, 'option' => 'com_login', 'task' => 'login', 'return' => base64_encode('index.php'), $t => 1));
        return strpos($this->last, 'name="passwd"') === false;
    }

    public function siteLogin($user, $pass)
    {
        $html = $this->get('index.php?option=com_users&view=login');
        $t = self::token($html);
        $h = self::hidden($html, 'com_users');
        $this->post('index.php?option=com_users&task=user.login', array_merge($h, array('username' => $user, 'password' => $pass, $t => 1)));
        return strpos($this->last, 'task=user.logout') !== false || strpos($this->last, 'logout') !== false;
    }

    /** Joomla system messages and fatal error markers in the last page */
    public function problems($html = null)
    {
        $html = $html === null ? $this->last : $html;
        $p = array();
        if (preg_match_all('#<div class="alert(?: alert-(\w+))?"[^>]*>(.*?)</div>\s*</div>#s', $html, $m, PREG_SET_ORDER)) {
            foreach ($m as $x) {
                if (in_array($x[1], array('success', 'info', 'message', 'notice'), true) || strpos($x[0], 'hidden') !== false) {
                    continue;
                }
                if ($x[1] === '' && strpos($x[2], 'alert-message') === false) {
                    continue;
                }
                $p[] = trim(preg_replace('/\s+/', ' ', strip_tags($x[2])));
            }
        }
        foreach (array('Fatal error:', 'Uncaught', 'SQLSTATE', 'syntax error', 'Error displaying the error page', 'JLIB_DATABASE', 'does not exist', 'Warning:', 'Notice:', 'Deprecated:') as $needle) {
            if (stripos($html, $needle) !== false) {
                $pos = stripos($html, $needle);
                $p[] = trim(preg_replace('/\s+/', ' ', strip_tags(substr($html, max(0, $pos - 200), 600))));
            }
        }
        return array_values(array_unique($p));
    }
}

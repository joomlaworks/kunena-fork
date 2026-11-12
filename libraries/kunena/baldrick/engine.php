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

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\String\StringHelper;

/**
 * Processes Baldrick's queue: triage (cheap model) -> spam / canned reply / request for details / related threads and
 * answer (capable model) -> reply, log, e-mail.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaBaldrickEngine
{
    /**
     * Minimum confidence of the triage step before a post is treated as spam
     *
     * @since 5.3.0
     */
    public const SPAM_CONFIDENCE = 0.8;

    /**
     * Longest reply posted (characters)
     *
     * @since 5.3.0
     */
    public const MAX_REPLY = 12000;

    /**
     * BBCode tags allowed in AI replies
     *
     * @var string[]
     * @since 5.3.0
     */
    public static $allowedTags = array('b', 'i', 'u', 's', 'url', 'code', 'list', '*', 'quote', 'ul', 'ol', 'li');

    /**
     * Processes due jobs.
     *
     * @param   integer  $limit    Jobs at most
     * @param   integer  $seconds  Stop starting new jobs after this many seconds
     *
     * @return  array  Results (job id => status: result)
     *
     * @since 5.3.0
     */
    public static function run($limit = 5, $seconds = 240)
    {
        $results = array();

        if (!KunenaBaldrick::hasTables()) {
            return $results;
        }

        static::loadLanguage();

        $start  = time();
        $params = KunenaBaldrick::getParams();

        KunenaBaldrick::migrateSignature();
        KunenaBaldrickQueue::cleanup(30);
        KunenaBaldrickUsage::cleanup((int) $params->get('log_days', 90));

        // Threads put back during a pause which ended early (the settings were saved, the provider answered): due now
        if (!KunenaBaldrick::isPaused()) {
            KunenaBaldrickQueue::release();
        }

        while (count($results) < $limit && time() - $start < $seconds) {
            // Paused after an account or configuration error: the queue waits (the first job after the pause tries again)
            if (KunenaBaldrick::isPaused()) {
                break;
            }

            $job = KunenaBaldrickQueue::claim();

            if (!$job) {
                break;
            }

            $results[$job->id] = static::process($job);
        }

        return $results;
    }

    /**
     * @return  void
     *
     * @since 5.3.0
     */
    public static function loadLanguage()
    {
        // English first, then the active language over it (translations may be incomplete)
        $lang = Factory::getLanguage();
        $lang->load('com_kunena.baldrick', KPATH_ADMIN, 'en-GB', false, false);
        $lang->load('com_kunena.baldrick', KPATH_ADMIN, null, true, false);
    }

    /**
     * Processes one job.
     *
     * @param   object  $job  Job
     *
     * @return  string  status: description
     *
     * @since 5.3.0
     */
    public static function process($job)
    {
        $params = KunenaBaldrick::getParams();
        $dryRun = (int) $params->get('dry_run', 0);
        $run    = (object) array(
            'job'     => $job,
            'start'   => microtime(true),
            'models'  => array(),
            'input'   => 0,
            'output'  => 0,
            'cache'   => 0,
            'cost'    => 0,
            'verdict' => '',
            'triage'  => null,
            'dryRun'  => $dryRun,
        );

        static::watch($run);

        try {
            return static::processJob($run);
        } finally {
            static::$current = null;
        }
    }

    /**
     * The job being processed, for the shutdown handler
     *
     * @var    object|null
     * @since  5.3.0
     */
    protected static $current = null;

    /**
     * A fatal error (the time or memory limit...) cannot be caught: a shutdown function records it in the activity log
     * and puts the thread back like any other error, instead of leaving it "being processed" without a word.
     *
     * @param   object  $run  Run
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function watch($run)
    {
        static $registered = false;

        static::$current = $run;

        if ($registered) {
            return;
        }

        $registered = true;

        register_shutdown_function(function () {
            $run = KunenaBaldrickEngine::current();
            $error = error_get_last();

            if (!$run || !$error || !in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR), true)) {
                return;
            }

            try {
                $message = 'PHP fatal error: ' . $error['message'] . ' (' . basename($error['file']) . ':' . $error['line'] . ')';

                if (stripos($error['message'], 'Maximum execution time') !== false) {
                    $message .= '. The server stopped the processing at its time limit (max_execution_time): set Processing '
                        . 'to "Command line only" and run kunena:baldrick:process from cron, or raise the limit.';
                }

                KunenaBaldrickEngine::fatal($run, $message);
            } catch (Throwable $e) {
                // Nothing more can be done at shutdown
            }
        });
    }

    /**
     * @return  object|null  The run in progress
     *
     * @since 5.3.0
     */
    public static function current()
    {
        return static::$current;
    }

    /**
     * Tells the recipients that a thread failed for good (the attempts are used up): the answer was never posted.
     *
     * @param   object  $run      Run
     * @param   string  $message  Error
     *
     * @return  string  The e-mail's outcome, for the activity log
     *
     * @since 5.3.0
     */
    protected static function mailError($run, $message)
    {
        try {
            $topic = KunenaForumTopicHelper::get((int) $run->job->topic_id, true);

            if (!$topic->exists()) {
                return '';
            }

            return static::mail($run, $topic, static::getPosts($topic, (int) $run->job->message_id), 'error', $message);
        } catch (Throwable $e) {
            return ' | E-mail failed: ' . $e->getMessage();
        }
    }

    /**
     * Records a fatal error of the run in progress (shutdown handler).
     *
     * @param   object  $run      Run
     * @param   string  $message  Error
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function fatal($run, $message)
    {
        static::$current = null;

        if (!KunenaBaldrickQueue::retry($run->job, $message)) {
            $message .= ' (gave up after ' . KunenaBaldrickQueue::MAX_ATTEMPTS . ' attempts)';
            $message .= static::mailError($run, $message);
        }

        static::log($run, 'error', $message);
    }

    /**
     * Processes one job (see process()).
     *
     * @param   object  $run  Run
     *
     * @return  string  status: description
     *
     * @since 5.3.0
     */
    protected static function processJob($run)
    {
        $job    = $run->job;
        $params = KunenaBaldrick::getParams();

        // The previous attempt stopped without finishing (PHP fatal error, time or memory limit, the process killed):
        // say so in the activity log, and give up after the usual number of attempts instead of trying for ever
        if (!empty($job->stalled)) {
            $stalled = 'The previous attempt stopped before finishing without an error of its own: the server ended the '
                . 'processing (e.g. PHP-FPM\'s request_terminate_timeout, or the process was killed). Run php cli/joomla.php '
                . 'kunena:baldrick:process --unlock to see whether it works from the terminal; if it does, set Processing to '
                . '"Command line only" and run that command from cron.';

            if ((int) $job->attempts > KunenaBaldrickQueue::MAX_ATTEMPTS) {
                return static::end($run, 'failed', 'error', $stalled . static::mailError($run, $stalled));
            }

            static::log($run, 'error', $stalled);
        }

        try {
            $topic  = KunenaForumTopicHelper::get($job->topic_id, true);
            $reason = static::check($job, $topic);

            if ($reason !== '') {
                return static::end($run, 'skipped', 'skipped', $reason);
            }

            KunenaBaldrick::ensureUser();

            $posts   = static::getPosts($topic);
            $trigger = null;

            foreach ($posts as $post) {
                if ($post['id'] === (int) $job->message_id) {
                    $trigger = $post;
                }
            }

            if (!$trigger) {
                // The queued post was unpublished, deleted or moved: the thread's newest post, if it is one Baldrick would
                // answer (a post made while the job waited, for example)
                $last   = end($posts);
                $why    = '';
                $latest = $last && $last['role'] === 'member' ? KunenaForumMessageHelper::get($last['id']) : null;

                if ($latest && $latest->exists() && KunenaBaldrick::getTriggerReason($latest, $why) !== '') {
                    $trigger           = $last;
                    $job->message_id   = $last['id'];
                    $run->job          = $job;
                } else {
                    return static::end($run, 'skipped', 'skipped', sprintf(
                        'The post #%d is no longer visible%s.',
                        (int) $job->message_id,
                        $last ? sprintf(', and the newest post (#%d) is not one Baldrick answers%s', $last['id'], $why ? ': ' . $why : '') : ''
                    ));
                }
            }

            // Stage 1: triage
            $result = static::call($run, 'triage', KunenaBaldrickPrompt::triageSystem(), KunenaBaldrickPrompt::triageMessage($topic, $posts), KunenaBaldrickPrompt::triageSchema(), 'low');
            $triage = KunenaBaldrickPrompt::normaliseTriage($result['data']);

            $run->triage  = $triage;
            $run->verdict = $triage['verdict'];
            $note         = sprintf('%s (%.2f): %s', $triage['verdict'], $triage['confidence'], $triage['reason']);

            switch ($triage['verdict']) {
                case 'spam':
                    return static::spam($run, $topic, $posts, $trigger, $note);

                case 'not_a_question':
                    return static::end($run, 'done', 'skipped', $note);

                case 'too_broad':
                    return static::reply($run, $topic, $posts, $trigger, static::canned($topic, $trigger, $triage), 'canned', $note);

                case 'needs_info':
                    $text = $triage['missing'];

                    if ($text !== '' && KunenaBaldrickPrompt::wantsTranslation($triage['language']) && $triage['missing_en'] !== '') {
                        $text .= "\n\n[b]English translation:[/b]\n" . $triage['missing_en'];
                    }

                    if ($text === '') {
                        return static::reply($run, $topic, $posts, $trigger, static::canned($topic, $trigger, $triage), 'canned', $note);
                    }

                    return static::reply($run, $topic, $posts, $trigger, $text, 'info', $note);
            }

            // Stage 2: related threads and the answer
            $related = KunenaBaldrickRetriever::search($triage['queries'], $topic->id, (int) $params->get('retrieval_topics', 5));
            $note   .= ' | ' . count($related) . ' related thread(s) (' . KunenaBaldrickRetriever::$lastMode . ' search, ' . KunenaBaldrickRetriever::$lastSeconds . ' s)';

            try {
                $result = static::call($run, 'answer', KunenaBaldrickPrompt::answerSystem(), KunenaBaldrickPrompt::answerMessage($topic, $posts, $related, $triage));
                $text   = trim($result['text']);

                if ($result['stop'] === 'max_tokens') {
                    throw new KunenaBaldrickException('The answer was cut off at the maximum number of tokens: raise "Max tokens" of the answer model.');
                }
            } catch (KunenaBaldrickException $e) {
                if ($e->retryable) {
                    throw $e;
                }

                // Refusals, unusable answers: fall back to a canned reply
                return static::reply($run, $topic, $posts, $trigger, static::canned($topic, $trigger, $triage), 'canned', $note . ' | ' . $e->getMessage());
            }

            if ($text === '' || strpos($text, 'NO_ANSWER') !== false && StringHelper::strlen($text) < 40) {
                return static::reply($run, $topic, $posts, $trigger, static::canned($topic, $trigger, $triage), 'canned', $note . ' | The answer model gave no answer.');
            }

            return static::reply($run, $topic, $posts, $trigger, static::sanitise($text, $job), 'replied', $note);
        } catch (Throwable $e) {
            // Throwable: PHP errors (TypeError...) too, which would otherwise end the background request silently
            $message = $e->getMessage();

            if (!$e instanceof Exception) {
                $message = get_class($e) . ': ' . $message . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            }

            // Invalid key, no credits, unknown model...: every thread would fail the same way. Pause, keep the queue,
            // tell the administrators once.
            if ($e instanceof KunenaBaldrickException && $e->account) {
                $first = KunenaBaldrick::pause($message);
                $pause = KunenaBaldrick::getPause();

                KunenaBaldrickQueue::defer($job, $pause['until'], 'Paused: ' . $message);
                static::log($run, 'error', 'Baldrick paused: ' . $message);

                if ($first || !$pause['notified']) {
                    try {
                        if (KunenaBaldrickMailer::sendPaused($message)) {
                            KunenaBaldrick::saveParams(array('pause_notified' => 1));
                        }
                    } catch (Exception $mailError) {
                        KunenaError::warning('Baldrick: ' . $mailError->getMessage());
                    }
                }

                return 'paused: ' . $message;
            }

            // Temporary provider errors, and unexpected errors (not the provider's verdict): try again later
            $temporary = $e instanceof KunenaBaldrickException ? $e->retryable : true;

            if ($temporary && KunenaBaldrickQueue::retry($job, $message, $e instanceof KunenaBaldrickException ? $e->retryAfter : 0)) {
                static::log($run, 'error', 'Will try again later: ' . $message);

                return 'retry: ' . $message;
            }

            KunenaBaldrickQueue::finish($job, 'failed', $message);
            static::log($run, 'error', $message . static::mailError($run, $message));

            return 'failed: ' . $message;
        }
    }

    /**
     * Rules checked when the job runs (and again just before posting): the thread may have changed since it was queued.
     *
     * @param   object            $job    Job
     * @param   KunenaForumTopic  $topic  Thread
     *
     * @return  string  Why the thread is skipped ('' to go ahead)
     *
     * @since 5.3.0
     */
    public static function check($job, KunenaForumTopic $topic)
    {
        $params = KunenaBaldrick::getParams();

        if (!KunenaBaldrick::isEnabled() || !KunenaBaldrick::isConfigured()) {
            return 'Baldrick is disabled or has no API key.';
        }

        if (!$topic->exists() || $topic->hold || $topic->moved_id) {
            return 'The thread is not visible.';
        }

        // After an outage, a reply days later would be odd
        $hours = (int) $params->get('max_wait_hours', 24);

        if ($hours > 0 && (int) $job->created < Factory::getDate()->toUnix() - $hours * 3600) {
            return 'The thread waited longer than ' . $hours . ' hours in the queue.';
        }

        $category = $topic->getCategory();

        if (!KunenaBaldrick::isCategoryAllowed($category)) {
            return 'Baldrick does not reply in this category.';
        }

        // Dry run (also for threads queued before it was switched on): only threads started by the staff
        if ((int) $params->get('dry_run', 0) && ($job->reason === 'revived' || !KunenaBaldrick::isStaff($topic->first_post_userid, $category))) {
            return 'Dry run: only threads started by administrators and moderators are answered.';
        }

        if (KunenaBaldrick::countReplies($topic->id) >= max(1, (int) $params->get('replies_per_topic', 3))) {
            return 'Baldrick already replied as often as allowed in this thread.';
        }

        $lastUser = (int) $topic->last_post_userid;

        if ($lastUser && $lastUser === KunenaBaldrick::getUserId()) {
            return 'The last post is Baldrick\'s.';
        }

        if (KunenaBaldrick::isStaff($lastUser, $category) && !((int) $params->get('dry_run', 0) && (int) $topic->last_post_id === (int) $job->message_id)) {
            return 'The last post comes from an administrator or moderator.';
        }

        $cap = (int) $params->get('daily_cap', 120);

        if ($cap > 0 && KunenaBaldrickUsage::countRecentReplies() >= $cap) {
            return 'The daily limit of replies has been reached.';
        }

        return '';
    }

    /**
     * Visible posts of a thread, oldest first.
     *
     * @param   KunenaForumTopic  $topic       Thread
     * @param   integer           $includeId   A post to include even if it is not visible
     *
     * @return  array  [['id', 'userid', 'author', 'role' (member, staff, assistant), 'time', 'text', 'hold'], ...]
     *
     * @since 5.3.0
     */
    public static function getPosts(KunenaForumTopic $topic, $includeId = 0)
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('m.id', 'm.userid', 'm.name', 'm.time', 'm.hold', 't.message')))
            ->from($db->quoteName('#__kunena_messages', 'm'))
            ->innerJoin($db->quoteName('#__kunena_messages_text', 't') . ' ON ' . $db->quoteName('t.mesid') . ' = ' . $db->quoteName('m.id'))
            ->where($db->quoteName('m.thread') . ' = ' . (int) $topic->id)
            ->where('(' . $db->quoteName('m.hold') . ' = 0' . ($includeId ? ' OR ' . $db->quoteName('m.id') . ' = ' . (int) $includeId : '') . ')')
            ->order($db->quoteName('m.time') . ' ASC, ' . $db->quoteName('m.id') . ' ASC');

        $category = $topic->getCategory();
        $baldrick = KunenaBaldrick::getUserId();
        $posts    = array();

        foreach ((array) $db->setQuery($query)->loadObjectList() as $row) {
            $userid = (int) $row->userid;
            $role   = $userid && $userid === $baldrick ? 'assistant' : (KunenaBaldrick::isStaff($userid, $category) ? 'staff' : 'member');

            $posts[] = array(
                'id'     => (int) $row->id,
                'userid' => $userid,
                'author' => (string) ($userid ? KunenaUserHelper::get($userid)->getName($row->name) : $row->name),
                'role'   => $role,
                'time'   => (int) $row->time,
                'hold'   => (int) $row->hold,
                'text'   => KunenaBaldrick::plainText($row->message),
                'bbcode' => (string) $row->message,
            );
        }

        return $posts;
    }

    /**
     * Calls a model and accounts for its tokens.
     *
     * @param   object      $run     Run
     * @param   string      $stage   triage or answer
     * @param   string      $system  System prompt
     * @param   string      $user    Message
     * @param   array|null  $schema  JSON schema
     * @param   string      $effort  Effort
     *
     * @return  array
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    protected static function call($run, $stage, $system, $user, ?array $schema = null, $effort = '')
    {
        $client = KunenaBaldrick::getClient($stage);

        try {
            $result = $client->complete($system, $user, $schema, $effort);
        } catch (KunenaBaldrickException $e) {
            $usage = $e->usage ? $e->usage : array('model' => $client->getModel(), 'usage' => array(), 'cost' => 0);
            static::account($run, $stage, $usage, true);

            throw $e;
        }

        static::account($run, $stage, $result, false);

        // The provider answers again: end a pause
        KunenaBaldrick::resume();

        return $result;
    }

    /**
     * @param   object   $run     Run
     * @param   string   $stage   Stage
     * @param   array    $result  Result (model, usage, cost)
     * @param   boolean  $error   The request failed
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function account($run, $stage, array $result, $error)
    {
        $usage = isset($result['usage']) ? $result['usage'] + array('input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0) : array('input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0);
        $cost  = isset($result['cost']) ? (int) $result['cost'] : 0;
        $model = isset($result['model']) ? (string) $result['model'] : '';

        KunenaBaldrickUsage::addRequest($model, $usage, $cost, $error);

        $run->models[$stage] = $model;
        $run->input         += $usage['input'];
        $run->output        += $usage['output'];
        $run->cache         += $usage['cache_read'] + $usage['cache_write'];
        $run->cost          += $cost;
    }

    /**
     * A spam verdict: unpublishes the post (the whole thread for a first post) when the triage step is confident, the
     * setting allows it and the author is not trusted; reports it otherwise.
     *
     * @param   object            $run      Run
     * @param   KunenaForumTopic  $topic    Thread
     * @param   array             $posts    Posts
     * @param   array             $trigger  The post
     * @param   string            $note     Triage note
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function spam($run, KunenaForumTopic $topic, array $posts, array $trigger, $note)
    {
        $params  = KunenaBaldrick::getParams();
        $action  = (string) $params->get('spam_action', 'unpublish');
        $sure    = $run->triage['confidence'] >= static::SPAM_CONFIDENCE;
        $trusted = $trigger['role'] !== 'member' || static::countUserPosts($trigger['userid']) >= (int) $params->get('spam_trusted_posts', 10);
        $first   = (int) $trigger['id'] === (int) $topic->first_post_id;

        if ($action === 'ignore') {
            return static::end($run, 'done', 'skipped', $note);
        }

        if ($action !== 'unpublish' || !$sure || $trusted || $run->dryRun) {
            $why = !$sure ? 'not confident enough' : ($trusted ? 'trusted author' : ($run->dryRun ? 'dry run' : 'report only'));
            $mailed = static::mail($run, $topic, $posts, 'spam', $note . "\n" . 'Not unpublished: ' . $why . '.');
            static::end($run, 'done', 'spam', $note . ' | Reported, not unpublished (' . $why . ').' . $mailed);

            return 'done: spam reported';
        }

        if ($first) {
            $ok = $topic->publish(KunenaForum::UNAPPROVED);
        } else {
            $message = KunenaForumMessageHelper::get($trigger['id'], true);
            $ok      = $message->exists() && $message->publish(KunenaForum::UNAPPROVED);
        }

        if (!$ok) {
            throw new KunenaBaldrickException('Could not unpublish the spam post.');
        }

        $what = $first ? 'The whole thread was unpublished (awaiting moderation).' : 'The post was unpublished (awaiting moderation).';

        $mailed = static::mail($run, $topic, $posts, 'spam_unpublished', $note . "\n" . $what);
        static::end($run, 'done', 'spam_unpublished', $note . ' | ' . $what . $mailed);

        return 'done: spam unpublished';
    }

    /**
     * @param   integer  $userid  User
     *
     * @return  integer  Published posts of the user
     *
     * @since 5.3.0
     */
    protected static function countUserPosts($userid)
    {
        if (!$userid) {
            return 0;
        }

        $db = Factory::getDbo();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_messages'))
                ->where($db->quoteName('userid') . ' = ' . (int) $userid)
                ->where($db->quoteName('hold') . ' = 0')
        )->loadResult();
    }

    /**
     * @param   KunenaForumTopic  $topic    Thread
     * @param   array             $trigger  The post
     * @param   array             $triage   Triage answer
     *
     * @return  string  A canned reply
     *
     * @since 5.3.0
     */
    protected static function canned(KunenaForumTopic $topic, array $trigger, array $triage)
    {
        $used = array();

        foreach (static::getPosts($topic) as $post) {
            if ($post['role'] === 'assistant') {
                $used[] = $post['bbcode'];
            }
        }

        return KunenaBaldrickPrompt::canned($topic, $trigger['author'], $triage['queries'], $used);
    }

    /**
     * Cleans an AI reply: only the allowed BBCode tags, links to this site made relative, length limit.
     *
     * @param   string  $text  Reply
     * @param   object  $job   Job
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function sanitise($text, $job = null)
    {
        $text = str_replace(array("\r\n", "\r"), "\n", (string) $text);
        $text = preg_replace('#^```[a-z]*\n|\n```$#im', '', $text);

        // Scripts, styles and embedded media go with their content
        $text = preg_replace('#<(script|style|iframe|object)\b[^>]*>.*?</\1\s*>#is', '', $text);
        $text = preg_replace('#\[(img|video|audio|iframe|embed|attachment|instagram|tweet|soundcloud|ebay|map)\b[^\]]*\].*?\[/\1\]#is', '', $text);
        $text = strip_tags($text);

        // Unknown tags are dropped, their content stays
        $allowed = static::$allowedTags;
        $text    = preg_replace_callback(
            '#\[(/?)([a-z*]+)(=[^\]]*)?\]#i',
            function ($m) use ($allowed) {
                return in_array(strtolower($m[2]), $allowed, true) ? $m[0] : '';
            },
            $text
        );

        // Links to this site become relative
        $roots = array(Uri::root());

        if ($job && !empty($job->site_url)) {
            $roots[] = $job->site_url;
        }

        foreach ($roots as $root) {
            $host = parse_url($root, PHP_URL_HOST);

            if (!$host) {
                continue;
            }

            $base = rtrim((string) parse_url($root, PHP_URL_PATH), '/');
            $text = preg_replace_callback(
                '#\[url=(?:https?:)?//' . preg_quote($host, '#') . '(?::\d+)?([^\]]*)\]#i',
                function ($m) use ($base) {
                    $path = $m[1] === '' ? '/' : $m[1];

                    if ($base !== '' && strpos($path, $base . '/') === 0) {
                        $path = substr($path, strlen($base) + 1);
                    } else {
                        $path = ltrim($path, '/');
                    }

                    return '[url=' . ($path === '' ? 'index.php' : $path) . ']';
                },
                $text
            );
        }

        $text = trim(preg_replace("#\n{3,}#", "\n\n", $text));

        if (StringHelper::strlen($text) > static::MAX_REPLY) {
            $text = StringHelper::substr($text, 0, static::MAX_REPLY) . ' [...]';
        }

        return $text;
    }

    /**
     * Posts a reply as Baldrick, logs it and sends the notification.
     *
     * @param   object            $run      Run
     * @param   KunenaForumTopic  $topic    Thread
     * @param   array             $posts    Posts
     * @param   array             $trigger  The post
     * @param   string            $text     Reply (BBCode, without signature)
     * @param   string            $action   replied, canned or info
     * @param   string            $note     Log note
     *
     * @return  string
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function reply($run, KunenaForumTopic $topic, array $posts, array $trigger, $text, $action, $note)
    {
        // The thread may have changed while the models were working
        $topic  = KunenaForumTopicHelper::get($topic->id, true);
        $reason = static::check($run->job, $topic);

        if ($reason !== '') {
            return static::end($run, 'skipped', 'skipped', $note . ' | ' . $reason);
        }

        // The signature is the account's forum signature, shown under the post (and editable at any time)
        $body    = trim($text);
        $message = static::post($topic, $body);

        $run->replyId = (int) $message->id;

        $posts[] = array(
            'id'     => (int) $message->id,
            'userid' => (int) $message->userid,
            'author' => (string) $message->name,
            'role'   => 'assistant',
            'time'   => (int) $message->time,
            'hold'   => 0,
            'text'   => KunenaBaldrick::plainText($body),
            'bbcode' => $body,
        );

        $mailed = static::mail($run, $topic, $posts, $action, $note);
        static::end($run, 'done', $action, $note . $mailed);

        return 'done: ' . $action;
    }

    /**
     * Saves Baldrick's post: published at once (even in moderated categories), without the flood and duplicate checks
     * and the CAPTCHA meant for forms (posts written by code never pass through the forms).
     *
     * @param   KunenaForumTopic  $topic  Thread
     * @param   string            $body   BBCode
     *
     * @return  KunenaForumMessage
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    public static function post(KunenaForumTopic $topic, $body)
    {
        $userid = KunenaBaldrick::ensureUser();
        $user   = KunenaUserHelper::get($userid);

        // As KunenaForumTopic::newReply(), with a post class that skips the checks meant for forms (see there)
        $message = new KunenaBaldrickMessage();
        $message->setTopic($topic);
        $message->parent  = $topic->first_post_id;
        $message->thread  = $topic->id;
        $message->catid   = $topic->category_id;
        $message->userid  = $userid;
        $message->name    = $user->getName('Baldrick');
        $message->subject = $topic->subject;
        $message->message = $body;
        $message->ip      = '';
        $message->hold    = KunenaForum::PUBLISHED;

        $saved = $message->save();

        if (!$saved) {
            throw new KunenaBaldrickException('Could not post the reply: ' . $message->getError());
        }

        // Post counter of the user (KunenaUser::save() refuses to save other users)
        $db = Factory::getDbo();
        $db->setQuery(KunenaDatabaseHelper::insertIgnore('#__kunena_users', array('userid'), 'VALUES (' . (int) $userid . ')'))->execute();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_users'))
                ->set($db->quoteName('posts') . ' = ' . $db->quoteName('posts') . ' + 1')
                ->where($db->quoteName('userid') . ' = ' . (int) $userid)
        )->execute();

        // Subscribers (usually the poster) are notified as for any reply
        try {
            $message->sendNotification();
        } catch (Exception $e) {
            KunenaError::warning('Baldrick: ' . $e->getMessage());
        }

        return $message;
    }

    /**
     * Ends a job and writes the log row.
     *
     * @param   object  $run     Run
     * @param   string  $status  Queue status
     * @param   string  $action  Log action
     * @param   string  $note    Note
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function end($run, $status, $action, $note)
    {
        KunenaBaldrickQueue::finish($run->job, $status, $action . ': ' . $note);
        static::log($run, $action, $note);

        return $status . ': ' . $action;
    }

    /**
     * @param   object  $run     Run
     * @param   string  $action  Action
     * @param   string  $note    Note
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function log($run, $action, $note)
    {
        KunenaBaldrickUsage::log(array(
            'topic_id'      => (int) $run->job->topic_id,
            'message_id'    => (int) $run->job->message_id,
            'reply_id'      => isset($run->replyId) ? (int) $run->replyId : 0,
            'verdict'       => $run->verdict,
            'action'        => $action,
            'models'        => implode(' > ', array_unique(array_values($run->models))),
            'input_tokens'  => $run->input,
            'output_tokens' => $run->output,
            'cache_tokens'  => $run->cache,
            'cost'          => $run->cost,
            'duration'      => (int) round((microtime(true) - $run->start) * 1000),
            'dry_run'       => $run->dryRun,
            'message'       => $note,
        ));
    }

    /**
     * @param   object            $run     Run
     * @param   KunenaForumTopic  $topic   Thread
     * @param   array             $posts   Posts
     * @param   string            $action  Action
     * @param   string            $note    Note
     *
     * @return  string  The outcome, for the activity log
     *
     * @since 5.3.0
     */
    protected static function mail($run, KunenaForumTopic $topic, array $posts, $action, $note)
    {
        // "Spam only": no e-mail for replies (spam and errors are always reported)
        if (!in_array($action, array('spam', 'spam_unpublished', 'error'), true) && (string) KunenaBaldrick::getParams()->get('notify_on', 'all') === 'spam') {
            return '';
        }

        // The outcome goes into the activity log: mail errors are otherwise invisible (Kunena shows warnings only in its
        // debug mode)
        try {
            $sent = KunenaBaldrickMailer::send($topic, $posts, $action, $note, $run);
        } catch (Exception $e) {
            return ' | E-mail failed: ' . $e->getMessage();
        }

        if ($sent === null) {
            return ' | No e-mail: no recipients are set (or none is a valid, active address).';
        }

        $errors = KunenaBaldrickMailer::$errors;

        return $errors
            ? sprintf(' | E-mail sent to %d of %d: %s', $sent, $sent + count($errors), implode('; ', array_unique($errors)))
            : sprintf(' | E-mail sent to %d.', $sent);
    }
}

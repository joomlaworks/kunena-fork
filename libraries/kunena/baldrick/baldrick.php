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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\String\StringHelper;

/**
 * Baldrick, the AI first responder: settings, triggers and the rules deciding when it may reply.
 *
 * The settings are the parameters of the "Kunena - Baldrick AI" plugin (plugins/kunena/baldrick). The plugin only wires
 * events; the work is done by the KunenaBaldrick* classes, shared with the administrator and the command line.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaBaldrick
{
    /**
     * Forum signature given to the account Baldrick creates (shown under its posts; edited in its forum profile)
     *
     * @since 5.3.0
     */
    public const SIGNATURE = '[i]Replied by Baldrick AI, my lord, using a cunning model (...and do remember that AI can make mistakes, so double check responses)[/i]';

    /**
     * The line 5.3.0 added to the text of every reply by default (removed from them by migrateSignature())
     *
     * @since 5.3.0
     */
    public const LEGACY_SIGNATURE = 'Replied by Baldrick AI using a cunning model my lord';

    /**
     * @var object|null|false  Plugin row (extension_id, enabled, params), false when not installed
     * @since 5.3.0
     */
    protected static $plugin = null;

    /**
     * @var Registry|null
     * @since 5.3.0
     */
    protected static $params = null;

    /**
     * @var boolean|null
     * @since 5.3.0
     */
    protected static $tables = null;

    /**
     * @var boolean  Posts created now are not questions (sample data, imports): Baldrick ignores them
     * @since 5.3.0
     */
    public static $suspended = false;

    /**
     * @var boolean  A thread was queued during this request
     * @since 5.3.0
     */
    public static $queued = false;

    /**
     * @param   boolean  $reload  Read again from the database
     *
     * @return  object|false  The plugin row
     *
     * @since 5.3.0
     */
    public static function getPlugin($reload = false)
    {
        if (static::$plugin === null || $reload) {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName(array('extension_id', 'enabled', 'params')))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('kunena'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('baldrick'));

            try {
                $row = $db->setQuery($query)->loadObject();
            } catch (Exception $e) {
                $row = null;
            }

            static::$plugin = $row ? $row : false;
            static::$params = null;
        }

        return static::$plugin;
    }

    /**
     * @return  boolean  The plugin is installed and enabled
     *
     * @since 5.3.0
     */
    public static function isEnabled()
    {
        $plugin = static::getPlugin();

        return $plugin && (int) $plugin->enabled === 1;
    }

    /**
     * @return  Registry  The plugin's settings
     *
     * @since 5.3.0
     */
    public static function getParams()
    {
        if (static::$params === null) {
            $plugin         = static::getPlugin();
            static::$params = new Registry($plugin ? (string) $plugin->params : '');
        }

        return static::$params;
    }

    /**
     * Sets the settings (tests, previews of unsaved settings).
     *
     * @param   Registry|null  $params  Settings, null to read them again
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function setParams(?Registry $params = null)
    {
        static::$params = $params;

        if ($params === null) {
            static::getPlugin(true);
        }
    }

    /**
     * Stores one setting in the plugin's parameters.
     *
     * @param   string  $name   Name
     * @param   mixed   $value  Value
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function saveParam($name, $value)
    {
        static::saveParams(array($name => $value));
    }

    /**
     * Stores settings in the plugin's parameters (null removes one).
     *
     * @param   array  $values  name => value
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function saveParams(array $values)
    {
        $plugin = static::getPlugin(true);

        if (!$plugin) {
            return;
        }

        $params = new Registry((string) $plugin->params);

        foreach ($values as $name => $value) {
            if ($value === null) {
                $params->remove($name);
            } else {
                $params->set($name, $value);
            }
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
                ->where($db->quoteName('extension_id') . ' = ' . (int) $plugin->extension_id)
        )->execute();

        static::getPlugin(true);
    }

    /**
     * @return  boolean  Baldrick's tables exist (created by Kunena's installation wizard)
     *
     * @since 5.3.0
     */
    public static function hasTables()
    {
        if (static::$tables === null) {
            $db = Factory::getDbo();

            try {
                static::$tables = in_array($db->replacePrefix('#__kunena_baldrick_queue'), (array) $db->getTableList(), true);
            } catch (Exception $e) {
                static::$tables = false;
            }
        }

        return static::$tables;
    }

    /**
     * Connection settings of a stage.
     *
     * @param   string  $stage  triage or answer
     *
     * @return  array
     *
     * @since 5.3.0
     */
    public static function getConnection($stage)
    {
        $params = static::getParams();
        $source = $stage === 'answer' && (int) $params->get('answer_same_connection', 1) ? 'triage' : $stage;

        return array(
            'provider'   => (string) $params->get($source . '_provider', 'anthropic'),
            'base_url'   => (string) $params->get($source . '_base_url', ''),
            'api_key'    => (string) $params->get($source . '_api_key', ''),
            'model'      => (string) $params->get($stage . '_model', $stage === 'answer' ? 'claude-sonnet-5-5' : 'claude-haiku-5-5'),
            'max_tokens' => (int) $params->get($stage . '_max_tokens', $stage === 'answer' ? 6000 : 2048),
            'price_in'   => (string) $params->get($stage . '_price_in', ''),
            'price_out'  => (string) $params->get($stage . '_price_out', ''),
            // The response timeout covers writing the whole answer (requests are not streamed)
            'timeout'    => (int) $params->get($stage . '_timeout', $stage === 'answer' ? 90 : 30),
            'connect'    => (int) $params->get('connect_timeout', 10),
            'fallbacks'  => (int) $params->get('anthropic_fallbacks', 1),
        );
    }

    /**
     * @param   string  $stage  triage or answer
     *
     * @return  KunenaBaldrickClient
     *
     * @since 5.3.0
     */
    public static function getClient($stage)
    {
        return new KunenaBaldrickClient(static::getConnection($stage));
    }

    /**
     * @return  boolean  An API key and the models are set
     *
     * @since 5.3.0
     */
    public static function isConfigured()
    {
        foreach (array('triage', 'answer') as $stage) {
            $conn = static::getConnection($stage);

            if ($conn['api_key'] === '' || $conn['model'] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Shows a notice at the top of Kunena's administrator pages while Baldrick is enabled but has no API key.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function adminNotice()
    {
        $app = Factory::getApplication();

        if ($app->input->getCmd('format', 'html') !== 'html' || $app->input->getCmd('tmpl') === 'component') {
            return;
        }

        if (!static::isEnabled()) {
            return;
        }

        $configured = static::isConfigured();
        $pause      = static::getPause();

        if ($configured && !$pause) {
            return;
        }

        KunenaBaldrickEngine::loadLanguage();

        $plugin = static::getPlugin();
        $edit   = Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $plugin->extension_id);

        if (!$configured) {
            $app->enqueueMessage(Text::sprintf('COM_KUNENA_BALDRICK_NOT_CONFIGURED', $edit), 'warning');

            return;
        }

        $app->enqueueMessage(
            Text::sprintf(
                'COM_KUNENA_BALDRICK_PAUSED_NOTICE',
                \Joomla\CMS\HTML\HTMLHelper::_('date', $pause['since'], Text::_('DATE_FORMAT_LC2')),
                htmlspecialchars($pause['reason'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $edit
            ),
            'warning'
        );
    }

    /**
     * @return  array|null  since, until (UNIX times), reason, notified: while Baldrick is paused after an error of the
     *                      account or the configuration
     *
     * @since 5.3.0
     */
    public static function getPause()
    {
        $params = static::getParams();
        $until  = (int) $params->get('pause_until', 0);

        if (!$until) {
            return null;
        }

        return array(
            'since'    => (int) $params->get('pause_since', 0),
            'until'    => $until,
            'reason'   => (string) $params->get('pause_reason', ''),
            'notified' => (int) $params->get('pause_notified', 0),
        );
    }

    /**
     * Pauses Baldrick after an error every request would get (invalid key, no credits...). Queued threads wait; the
     * provider is tried again after the pause, and saving the plugin's settings ends it at once.
     *
     * @param   string  $reason  Error
     *
     * @return  boolean  The pause started now (false: it was already paused)
     *
     * @since 5.3.0
     */
    public static function pause($reason)
    {
        $pause   = static::getPause();
        $minutes = max(1, (int) static::getParams()->get('pause_minutes', 15));
        $now     = Factory::getDate()->toUnix();

        static::saveParams(array(
            'pause_since'    => $pause ? $pause['since'] : $now,
            'pause_until'    => $now + $minutes * 60,
            'pause_reason'   => StringHelper::substr((string) $reason, 0, 500),
            'pause_notified' => $pause ? $pause['notified'] : 0,
        ));

        return !$pause;
    }

    /**
     * Ends the pause (a request succeeded).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function resume()
    {
        if (static::getPause()) {
            static::saveParams(array('pause_since' => null, 'pause_until' => null, 'pause_reason' => null, 'pause_notified' => null));
        }
    }

    /**
     * @return  boolean  Paused, and the pause has not run out yet
     *
     * @since 5.3.0
     */
    public static function isPaused()
    {
        $pause = static::getPause();

        return $pause && $pause['until'] > Factory::getDate()->toUnix();
    }

    /**
     * @return  integer  Baldrick's user ID (0 when it has none yet)
     *
     * @since 5.3.0
     */
    public static function getUserId()
    {
        $id = (int) static::getParams()->get('user_id', 0);

        if ($id && !static::userExists($id)) {
            return 0;
        }

        return $id;
    }

    /**
     * Whether a Joomla account exists, without Factory::getUser(), which warns about deleted accounts (posts keep the
     * ids of their deleted authors).
     *
     * @param   integer  $userid  User id
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function userExists($userid)
    {
        static $cache = array();

        $userid = (int) $userid;

        if ($userid <= 0) {
            return false;
        }

        if (!isset($cache[$userid])) {
            $db = Factory::getDbo();

            $cache[$userid] = (bool) $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('id') . ' = ' . $userid)
            )->loadResult();
        }

        return $cache[$userid];
    }

    /**
     * Baldrick's user, created when none is selected in the settings: username "baldrick", no usable password
     * (random), an example.invalid address, so the account can never be used to log in or receive mail.
     *
     * @return  integer  User ID
     *
     * @throws  KunenaBaldrickException
     * @since 5.3.0
     */
    public static function ensureUser()
    {
        $id = static::getUserId();

        if ($id) {
            return $id;
        }

        $db       = Factory::getDbo();
        $username = 'baldrick';

        for ($i = 2; $i < 100; $i++) {
            $exists = $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('username') . ' = ' . $db->quote($username))
            )->loadResult();

            if (!$exists) {
                break;
            }

            $username = 'baldrick' . $i;
        }

        $config = \Joomla\CMS\Component\ComponentHelper::getParams('com_users');
        $user   = new \Joomla\CMS\User\User();
        $data   = array(
            'name'     => 'Baldrick',
            'username' => $username,
            'email'    => $username . '@example.invalid',
            'password' => \Joomla\CMS\User\UserHelper::genRandomPassword(32),
            'block'    => 0,
            'groups'   => array((int) $config->get('new_usertype', 2)),
        );
        $data['password2'] = $data['password'];

        if (!$user->bind($data) || !$user->save()) {
            throw new KunenaBaldrickException('Could not create Baldrick\'s user: ' . $user->getError());
        }

        static::saveParam('user_id', (int) $user->id);
        static::$params = null;

        static::setSignature((int) $user->id, static::SIGNATURE);

        return (int) $user->id;
    }

    /**
     * Sets a user's forum signature, through the table: KunenaUser::save() only lets moderators save other users'
     * profiles, and Baldrick runs as the visitor (or nobody, on the command line).
     *
     * @param   integer  $userid     User
     * @param   string   $signature  BBCode
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function setSignature($userid, $signature)
    {
        $kuser            = KunenaUserHelper::get((int) $userid, true);
        $kuser->signature = (string) $signature;

        $table = $kuser->getTable();
        $table->bind($kuser->getProperties(), array('name', 'username', 'email', 'blocked', 'registerDate', 'lastvisitDate'));
        $table->exists($kuser->exists());

        $ok = $table->check() && $table->store();

        KunenaUserHelper::get((int) $userid, true);

        return $ok;
    }

    /**
     * Until 5.3.0's first update, a "Signature" setting was added to the text of every reply. Once: moves it to the
     * account's forum signature (if that is empty), removes it from the replies already posted, and drops the setting.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function migrateSignature()
    {
        $params = static::getParams();

        if (!$params->exists('signature')) {
            return;
        }

        $setting = trim((string) $params->get('signature', ''));
        $userid  = static::getUserId();

        if ($userid) {
            if ($setting !== '' && trim((string) KunenaUserHelper::get($userid)->signature) === '') {
                static::setSignature($userid, '[i]' . $setting . '[/i]');
            }

            $lines = array();

            foreach (array_unique(array($setting, static::LEGACY_SIGNATURE)) as $text) {
                if ($text !== '') {
                    $lines[] = '[i]' . str_replace(array('[', ']'), array('(', ')'), $text) . '[/i]';
                }
            }

            $db   = Factory::getDbo();
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(array('t.mesid', 't.message')))
                    ->from($db->quoteName('#__kunena_messages_text', 't'))
                    ->innerJoin($db->quoteName('#__kunena_messages', 'm') . ' ON ' . $db->quoteName('m.id') . ' = ' . $db->quoteName('t.mesid'))
                    ->where($db->quoteName('m.userid') . ' = ' . (int) $userid)
            )->loadObjectList();

            foreach ((array) $rows as $row) {
                $text = rtrim((string) $row->message);

                foreach ($lines as $line) {
                    if ($line !== '' && substr($text, -strlen($line)) === $line) {
                        $db->setQuery(
                            $db->getQuery(true)
                                ->update($db->quoteName('#__kunena_messages_text'))
                                ->set($db->quoteName('message') . ' = ' . $db->quote(rtrim(substr($text, 0, -strlen($line)))))
                                ->where($db->quoteName('mesid') . ' = ' . (int) $row->mesid)
                        )->execute();

                        break;
                    }
                }
            }
        }

        static::saveParams(array('signature' => null));
        static::$params = null;
    }

    /**
     * @param   integer                   $userid    User ID
     * @param   KunenaForumCategory|null  $category  Category
     *
     * @return  boolean  The user is a Kunena administrator or moderator (of the category), or a Super User
     *
     * @since 5.3.0
     */
    public static function isStaff($userid, ?KunenaForumCategory $category = null)
    {
        static $cache = array();

        $userid = (int) $userid;

        // A deleted account (its posts remain) is nobody's staff
        if (!$userid || !static::userExists($userid)) {
            return false;
        }

        $key = $userid . ':' . ($category ? (int) $category->id : 0);

        if (!isset($cache[$key])) {
            $user        = KunenaUserHelper::get($userid);
            $cache[$key] = Factory::getUser($userid)->authorise('core.admin') || $user->isAdmin($category) || $user->isModerator($category);
        }

        return $cache[$key];
    }

    /**
     * Categories Baldrick answers in.
     *
     * @param   KunenaForumCategory  $category  Category
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isCategoryAllowed(KunenaForumCategory $category)
    {
        $params  = static::getParams();
        $include = array_filter(array_map('intval', (array) $params->get('categories_include', array())));
        $exclude = array_filter(array_map('intval', (array) $params->get('categories_exclude', array())));

        if (!$category->exists() || (int) $category->published !== 1) {
            return false;
        }

        if ($include && !in_array((int) $category->id, $include, true)) {
            return false;
        }

        if ($exclude && in_array((int) $category->id, $exclude, true)) {
            return false;
        }

        if ((int) $params->get('public_only', 1) && !in_array((int) $category->id, static::getPublicCategories(), true)) {
            return false;
        }

        return true;
    }

    /**
     * Categories guests can read: the only content Baldrick learns from (and, by default, answers in).
     *
     * @return  integer[]
     *
     * @since 5.3.0
     */
    public static function getPublicCategories()
    {
        return KunenaForumCategoryHelper::getGuestReadable();
    }

    /**
     * Number of Baldrick's visible posts in a thread.
     *
     * @param   integer  $topicId  Thread
     *
     * @return  integer
     *
     * @since 5.3.0
     */
    public static function countReplies($topicId)
    {
        $userid = static::getUserId();

        if (!$userid) {
            return 0;
        }

        $db = Factory::getDbo();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_messages'))
                ->where($db->quoteName('thread') . ' = ' . (int) $topicId)
                ->where($db->quoteName('userid') . ' = ' . $userid)
                ->where($db->quoteName('hold') . ' = 0')
        )->loadResult();
    }

    /**
     * Called when a post becomes visible (new or approved): queues the thread when Baldrick should look at it.
     *
     * @param   KunenaForumMessage  $message  The post
     *
     * @return  boolean  Queued
     *
     * @since 5.3.0
     */
    public static function onPost(KunenaForumMessage $message)
    {
        try {
            $reason = static::getTriggerReason($message);

            if (!$reason) {
                return false;
            }

            $site = '';

            if (Factory::getApplication()->isClient('site') || Factory::getApplication()->isClient('administrator')) {
                $site = Uri::root();
            }

            if (KunenaBaldrickQueue::add($message->thread, $message->id, $reason, $site)) {
                static::$queued = true;

                return true;
            }
        } catch (Exception $e) {
            // Never break posting
            KunenaError::warning('Baldrick: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Decides whether a post that became visible should be answered.
     *
     * @param   KunenaForumMessage  $message  The post
     * @param   string              $why      Set to the rule which stopped it (English, for the command line)
     *
     * @return  string  new, revived, followup or '' (not queued)
     *
     * @since 5.3.0
     */
    public static function getTriggerReason(KunenaForumMessage $message, &$why = null)
    {
        $why = '';

        if (static::$suspended) {
            $why = 'Baldrick is suspended during a bulk import.';

            return '';
        }

        if (!static::isEnabled() || !static::isConfigured() || !static::hasTables()) {
            $why = 'The plugin is disabled, has no API key and models, or its tables are missing.';

            return '';
        }

        $params = static::getParams();
        $topic  = $message->getTopic();

        if ($message->hold || $topic->hold || !$topic->exists()) {
            $why = 'The post or its thread is not published (unapproved, deleted or missing).';

            return '';
        }

        $userid = (int) $message->userid;

        if ($userid && $userid === static::getUserId()) {
            $why = 'The post is by Baldrick.';

            return '';
        }

        // Posts moved between threads or approved long after they were written are not new questions
        if ($message->time < Factory::getDate()->toUnix() - 30 * 86400) {
            $why = 'The post was written more than 30 days ago.';

            return '';
        }

        $category = $message->getCategory();

        if (!static::isCategoryAllowed($category)) {
            $why = 'The category is excluded, not included, unpublished, or (with "Public categories only") not readable by guests.';

            return '';
        }

        $isNew    = !$message->parent || (int) $message->id === (int) $topic->first_post_id;
        $staff    = static::isStaff($userid, $category);
        $previous = $isNew ? null : static::getPreviousPost($message);

        // The thread's starter answers Baldrick (the details it asked for, a further question): a conversation
        $followup = $previous && $userid && (int) $previous->userid === static::getUserId() && $userid === (int) $topic->first_post_userid;

        if ((int) $params->get('dry_run', 0)) {
            // Testing: only threads started by administrators and moderators (new, and their follow-ups)
            if (!$staff || !($isNew || $followup)) {
                $why = 'Dry run is on: only new threads (and their follow-ups) by administrators and moderators are answered.';

                return '';
            }
        } elseif ($staff) {
            // The last post comes from the staff: they are handling the thread
            $why = 'The poster is staff (Super User, Kunena administrator, or Kunena moderator of this category).';

            return '';
        }

        if (!$isNew && !$followup) {
            $days = max(1, (int) $params->get('inactivity_days', 7));

            if (!$previous || $message->time - (int) $previous->time < $days * 86400) {
                $why = $previous
                    ? sprintf('The previous published post (#%d) is less than %d days older than this one.', (int) $previous->id, $days)
                    : 'The thread has no earlier published post.';

                return '';
            }
        }

        $minLength = max(0, (int) $params->get('min_length', 20));
        $length    = StringHelper::strlen(static::plainText($message->message));

        if ($length < $minLength) {
            $why = sprintf('The post has %d characters of text; the minimum is %d.', $length, $minLength);

            return '';
        }

        $replies = static::countReplies($topic->id);

        if ($replies >= max(1, (int) $params->get('replies_per_topic', 3))) {
            $why = sprintf('Baldrick has already replied %d times in this thread (the limit).', $replies);

            return '';
        }

        return $isNew ? 'new' : ($followup ? 'followup' : 'revived');
    }

    /**
     * @param   KunenaForumMessage  $message  Post
     *
     * @return  object|null  The previous visible post in the thread (id, userid, time)
     *
     * @since 5.3.0
     */
    protected static function getPreviousPost(KunenaForumMessage $message)
    {
        $db = Factory::getDbo();

        return $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'userid', 'time')))
                ->from($db->quoteName('#__kunena_messages'))
                ->where($db->quoteName('thread') . ' = ' . (int) $message->thread)
                ->where($db->quoteName('hold') . ' = 0')
                ->where($db->quoteName('id') . ' != ' . (int) $message->id)
                ->where($db->quoteName('time') . ' <= ' . (int) $message->time)
                ->order($db->quoteName('time') . ' DESC, ' . $db->quoteName('id') . ' DESC'),
            0,
            1
        )->loadObject();
    }

    /**
     * Converts post BBCode to plain text for the models and the e-mails: keeps code and link targets, drops hidden and
     * confidential parts, quotes and media.
     *
     * @param   string  $text  BBCode
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function plainText($text)
    {
        return KunenaHtmlParser::plainText($text);
    }

    /**
     * Relative link to a thread (routed when the post is displayed).
     *
     * @param   integer  $catid    Category
     * @param   integer  $topicId  Thread
     * @param   integer  $postId   Post (0 for none)
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function topicLink($catid, $topicId, $postId = 0)
    {
        return 'index.php?option=com_kunena&view=topic&catid=' . (int) $catid . '&id=' . (int) $topicId
            . ($postId ? '&mesid=' . (int) $postId : '');
    }

    /**
     * Writes and unlocks the PHP session, keeping its data readable: Joomla's session object stays "active" and reads
     * $_SESSION, so nothing tries to start the session again after the response has been sent.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function releaseSession()
    {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    /**
     * @return  string  Key of the background request processing the queue
     *
     * @since 5.3.0
     */
    public static function runKey()
    {
        return hash_hmac('sha256', 'kunena-baldrick-run', (string) Factory::getConfig()->get('secret'));
    }

    /**
     * Starts processing the queue without making the visitor wait: after the response has been sent (PHP-FPM), or with a
     * background request to the site itself which does not wait for the answer.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function kick()
    {
        if ((string) static::getParams()->get('processing', 'auto') !== 'auto') {
            return;
        }

        $finish = function_exists('fastcgi_finish_request') ? 'fastcgi_finish_request' : (function_exists('litespeed_finish_request') ? 'litespeed_finish_request' : '');

        if ($finish) {
            // Release the visitor's session (its lock) without closing Joomla's session object: once the response is sent,
            // Joomla could not start the session again (headers sent), and the processing reads it (the current user...)
            static::releaseSession();

            // Before the response ends: some servers stop the script as soon as the visitor's connection is gone
            ignore_user_abort(true);
            @set_time_limit(300);

            $finish();

            KunenaBaldrickEngine::run(3, 240);

            return;
        }

        $url = Uri::root() . 'index.php?option=com_ajax&group=kunena&plugin=baldrick&format=raw';

        try {
            $http = \Joomla\CMS\Http\HttpFactory::getHttp(new Registry(), array('curl', 'stream'));
            $http->post($url, array('task' => 'run', 'key' => static::runKey()), array(), 1);
        } catch (Exception $e) {
            // The request is not meant to be waited for: a timeout is expected
        }
    }
}

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Sampledata
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

/**
 * Sample data: a complete fictional forum to style and test Kunena with.
 *
 * Installs categories (sections, sub-categories, a locked, a moderated and a staff-only category), users (with
 * unknown random passwords, example.invalid e-mail addresses and generated avatars), moderators, an announcement, and threads with replies
 * spread over the last six months: BBCode, polls with votes, sticky and locked threads, thank-yous, image attachments,
 * unapproved and trashed posts, and a long multi-page thread. Everything is created through Kunena's own API, so it
 * works on every database Kunena supports.
 *
 * Every created item is recorded in #__kunena_sampledata; removal deletes exactly those items. Posts which other
 * users added to sample threads are kept (with their thread), and so are sample categories which still contain
 * other content.
 *
 * Both operations work in steps limited by time (administrator AJAX), or in one go (command line).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaSampledata
{
    /**
     * Tracking table.
     *
     * @since 5.3.0
     */
    public const TABLE = '#__kunena_sampledata';

    /**
     * Documentation IP address range (RFC 5737) used for the sample posts.
     *
     * @since 5.3.0
     */
    public const IP_PREFIX = '192.0.2.';

    /**
     * @var array|null
     * @since 5.3.0
     */
    protected static $data = null;

    /**
     * The data set (libraries/kunena/sampledata/data.php).
     *
     * @return  array
     *
     * @since 5.3.0
     */
    public static function getData()
    {
        if (static::$data === null) {
            static::$data = include __DIR__ . '/data.php';
        }

        return static::$data;
    }

    /**
     * What installing creates.
     *
     * @return  array  Counts: categories, users, topics, posts.
     *
     * @since 5.3.0
     */
    public static function getPlan()
    {
        $data  = static::getData();
        $posts = 0;

        foreach ($data['topics'] as $topic) {
            $posts += 1 + (int) $topic['replies'];
        }

        return array(
            'categories' => count($data['categories']),
            'users'      => count($data['users']),
            'topics'     => count($data['topics']),
            'posts'      => $posts,
        );
    }

    /**
     * Is (some) sample data installed?
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isInstalled()
    {
        $counts = static::getCounts();

        return array_sum($counts) > 0;
    }

    /**
     * Installed sample items, by type.
     *
     * @return  array  ['category' => n, 'user' => n, 'topic' => n, 'announcement' => n]
     *
     * @since 5.3.0
     */
    public static function getCounts()
    {
        $counts = array('category' => 0, 'user' => 0, 'topic' => 0, 'announcement' => 0, 'poll' => 0);

        if (!static::tableExists()) {
            return $counts;
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('item_type') . ', COUNT(*) AS ' . $db->quoteName('total'))
                ->from($db->quoteName(static::TABLE))
                ->group($db->quoteName('item_type'))
        );

        foreach ((array) $db->loadObjectList() as $row) {
            $counts[$row->item_type] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Installs the sample data, step by step.
     *
     * @param   integer  $timeLimit  Stop starting new work after this many seconds (0: no limit).
     * @param   array    $state      State between calls (empty on the first call); updated.
     *
     * @return  array  ['done' => bool, 'progress' => 0-100, 'message' => text]
     *
     * @throws  Exception
     * @since 5.3.0
     */
    public static function install($timeLimit, array &$state)
    {
        // The sample posts are not questions: Baldrick AI must not answer them
        $suspended = class_exists('KunenaBaldrick') ? KunenaBaldrick::$suspended : null;

        if ($suspended !== null) {
            KunenaBaldrick::$suspended = true;
        }

        try {
            return static::installSteps($timeLimit, $state);
        } finally {
            if ($suspended !== null) {
                KunenaBaldrick::$suspended = $suspended;
            }
        }
    }

    /**
     * @param   integer  $timeLimit  Seconds to work (0: no limit)
     * @param   array    $state      State between steps
     *
     * @return  array
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function installSteps($timeLimit, array &$state)
    {
        $start = microtime(true);
        $data  = static::getData();
        $state += array('step' => 'base', 'next' => 0, 'categories' => array(), 'users' => array(), 'now' => time(), 'replies' => 0);

        if ($state['step'] === 'base') {
            if (static::isInstalled()) {
                throw new RuntimeException('The sample data is already installed. Remove it first.');
            }

            static::ensureTable();
            $state['categories'] = static::createCategories();
            $state['users']      = static::createUsers($state['now']);
            static::createModerators($state['categories'], $state['users']);
            static::createAnnouncement($state['users'], $state['now']);
            $state['step'] = 'topics';
        }

        if ($state['step'] === 'topics') {
            $total = count($data['topics']);

            while ($state['next'] < $total) {
                static::createTopic($state['next'], $state);
                $state['next']++;

                if ($timeLimit > 0 && microtime(true) - $start >= $timeLimit && $state['next'] < $total) {
                    return array(
                        'done'     => false,
                        'progress' => (int) floor(5 + 90 * $state['next'] / $total),
                        'message'  => sprintf('%d of %d threads created...', $state['next'], $total),
                    );
                }
            }

            $state['step'] = 'finish';
        }

        static::recount();
        static::clearCaches();

        return array('done' => true, 'progress' => 100, 'message' => 'The sample data is installed.');
    }

    /**
     * Removes the sample data, step by step.
     *
     * @param   integer  $timeLimit  Stop starting new work after this many seconds (0: no limit).
     * @param   array    $state      State between calls (empty on the first call); updated.
     *
     * @return  array  ['done' => bool, 'progress' => 0-100, 'message' => text, 'kept' => [notes]]
     *
     * @since 5.3.0
     */
    public static function remove($timeLimit, array &$state)
    {
        $start = microtime(true);
        $state += array('step' => 'topics', 'total' => null, 'kept' => array());

        if (!static::tableExists()) {
            return array('done' => true, 'progress' => 100, 'message' => 'No sample data is installed.', 'kept' => array());
        }

        $sampleUsers = static::getTracked('user');

        if ($state['total'] === null) {
            $state['total'] = max(1, count(static::getTracked('topic')));
        }

        if ($state['step'] === 'topics') {
            foreach (static::getTracked('topic') as $topicId) {
                static::removeTopic($topicId, $sampleUsers, $state);
                static::untrack('topic', $topicId);

                if ($timeLimit > 0 && microtime(true) - $start >= $timeLimit) {
                    $left = count(static::getTracked('topic'));

                    if ($left) {
                        return array(
                            'done'     => false,
                            'progress' => (int) floor(90 * ($state['total'] - $left) / $state['total']),
                            'message'  => sprintf('%d threads left to remove...', $left),
                            'kept'     => $state['kept'],
                        );
                    }
                }
            }

            $state['step'] = 'rest';
        }

        static::removePolls();
        static::removeAnnouncements();
        static::removeCategories($state);
        static::removeUsers($sampleUsers);
        static::recount();
        static::clearCaches();

        // Nothing is tracked any more: drop the tracking table.
        if (!static::isInstalled()) {
            $db = Factory::getDbo();
            $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName(static::TABLE))->execute();
        }

        return array('done' => true, 'progress' => 100, 'message' => 'The sample data has been removed.', 'kept' => $state['kept']);
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Installation
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * @return  array  Category key => id
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function createCategories()
    {
        $data     = static::getData();
        $ids      = array();
        $ordering = array();
        $db       = Factory::getDbo();

        // Sections go after the existing ones
        $db->setQuery('SELECT MAX(' . $db->quoteName('ordering') . ') FROM ' . $db->quoteName('#__kunena_categories') . ' WHERE ' . $db->quoteName('parent_id') . ' = 0');
        $ordering[0] = (int) $db->loadResult();

        foreach ($data['categories'] as $key => $def) {
            $parent = isset($def['parent']) ? $ids[$def['parent']] : 0;
            $ordering[$parent] = isset($ordering[$parent]) ? $ordering[$parent] + 1 : 1;

            $category = new KunenaForumCategory();
            $category->bind(
                array(
                    'parent_id'       => $parent,
                    'name'            => $def['name'],
                    'alias'           => static::freeAlias($def['name']),
                    'description'     => $def['description'],
                    'headerdesc'      => '',
                    'topictemplate'   => '',
                    'class_sfx'       => '',
                    'icon'            => '',
                    'published'       => 1,
                    'locked'          => empty($def['locked']) ? 0 : 1,
                    'review'          => empty($def['review']) ? 0 : 1,
                    'allow_polls'     => empty($def['polls']) ? 0 : 1,
                    'allow_anonymous' => 0,
                    'allow_ratings'   => 1,
                    'accesstype'      => 'joomla.group',
                    'access'          => 0,
                    // Public (1), or Manager (6) and the groups below it in the tree (Administrator, Super Users)
                    'pub_access'      => isset($def['access']) && $def['access'] === 'staff' ? 6 : 1,
                    'pub_recurse'     => 1,
                    'admin_access'    => 0,
                    'admin_recurse'   => 1,
                    'channels'        => 'THIS',
                    'iconset'         => 'default',
                    'topic_ordering'  => 'lastpost',
                    'ordering'        => $ordering[$parent],
                    'params'          => '{}',
                )
            );

            if (!$category->save()) {
                throw new RuntimeException(sprintf('Category "%s": %s', $def['name'], $category->getError()));
            }

            $ids[$key] = (int) $category->id;
            static::track('category', $category->id);
        }

        KunenaAccess::getInstance()->clearCache();

        return $ids;
    }

    /**
     * An alias which isn't used yet.
     *
     * @param   string  $name  Category name
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function freeAlias($name)
    {
        $db   = Factory::getDbo();
        $base = KunenaRoute::stringURLSafe('sample ' . $name);

        for ($i = 1; $i < 100; $i++) {
            $alias = $i > 1 ? "{$base}-{$i}" : $base;
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__kunena_aliases'))
                    ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
            );

            if (!(int) $db->loadResult()) {
                return $alias;
            }
        }

        return $base . '-' . substr(md5(uniqid('', true)), 0, 6);
    }

    /**
     * @param   integer  $now  Reference time
     *
     * @return  array  User key => id
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function createUsers($now)
    {
        $data = static::getData();
        $ids  = array();
        $i    = 0;

        foreach ($data['users'] as $key => $def) {
            $i++;
            $username = static::freeUsername($def['username']);
            $user     = new User();
            $password = UserHelper::genRandomPassword(32);

            $values = array(
                'name'      => $def['name'],
                'username'  => $username,
                'email'     => $username . '@example.invalid',
                'password'  => $password,
                'password2' => $password,
                // Active, so the forum shows them like normal members. Nobody can sign in with them: the password is
                // random and never stored or shown, and example.invalid addresses can't receive password reset e-mails.
                'block'     => 0,
                'groups'    => array(2),
            );

            // User::bind() takes its argument by reference
            $user->bind($values);
            $user->registerDate = Factory::getDate($now - (200 + 5 * $i) * 86400)->toSql();

            if (!$user->save()) {
                throw new RuntimeException(sprintf('User "%s": %s', $username, $user->getError()));
            }

            $ids[$key] = (int) $user->id;
            static::track('user', $user->id);

            $kuser               = KunenaUserHelper::get($user->id, true);
            $kuser->status       = 0;
            $kuser->status_text  = '';
            $kuser->signature    = $def['signature'];
            $kuser->personalText = $def['personal'];
            $kuser->location     = $def['location'];
            $kuser->gender       = (int) $def['gender'];
            $kuser->karma        = (int) $def['karma'];
            $kuser->ip           = static::IP_PREFIX . (10 + $i);

            if (!empty($def['website'])) {
                $kuser->websitename = $def['website'][0];
                $kuser->websiteurl  = $def['website'][1];
            }

            if (!isset($def['avatar']) || $def['avatar'] !== false) {
                $avatar = static::createAvatar($username);

                if ($avatar) {
                    $kuser->avatar = $avatar;
                }
            }

            // Stored through the table: KunenaUser::save() only lets moderators save other users' profiles, and on the
            // command line Kunena's "current user" isn't the Super User the command runs as.
            $table = $kuser->getTable();
            $table->bind($kuser->getProperties(), array('name', 'username', 'email', 'blocked', 'registerDate', 'lastvisitDate'));
            $table->exists($kuser->exists());

            if (!$table->check() || !$table->store()) {
                throw new RuntimeException(sprintf('Kunena profile of "%s": %s', $username, $table->getError()));
            }

            KunenaUserHelper::get($user->id, true);
        }

        return $ids;
    }

    /**
     * A username which isn't used yet.
     *
     * @param   string  $username  Wanted username
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function freeUsername($username)
    {
        for ($i = 1; $i < 100; $i++) {
            $candidate = $i > 1 ? "{$username}{$i}" : $username;

            if (!UserHelper::getUserId($candidate)) {
                $db = Factory::getDbo();
                $db->setQuery(
                    $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__users'))
                        ->where($db->quoteName('email') . ' = ' . $db->quote($candidate . '@example.invalid'))
                );

                if (!(int) $db->loadResult()) {
                    return $candidate;
                }
            }
        }

        return $username . '-' . substr(md5(uniqid('', true)), 0, 6);
    }

    /**
     * Generates an identicon avatar (PNG) in media/kunena/avatars/users.
     *
     * @param   string  $username  Username
     *
     * @return  string|null  Avatar path relative to media/kunena/avatars
     *
     * @since 5.3.0
     */
    protected static function createAvatar($username)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $hash = md5('kunena-sample-' . $username);
        $size = 250;
        $cell = 50;
        $img  = imagecreatetruecolor($size, $size);
        $bg   = imagecolorallocate($img, 240, 240, 240);
        $fg   = imagecolorallocate($img, hexdec(substr($hash, 0, 2)) % 180 + 30, hexdec(substr($hash, 2, 2)) % 180 + 30, hexdec(substr($hash, 4, 2)) % 180 + 30);

        imagefilledrectangle($img, 0, 0, $size, $size, $bg);

        // 5x5 grid, mirrored horizontally
        for ($y = 0; $y < 5; $y++) {
            for ($x = 0; $x < 3; $x++) {
                if (hexdec($hash[6 + $y * 3 + $x]) % 2) {
                    imagefilledrectangle($img, $x * $cell, $y * $cell, ($x + 1) * $cell - 1, ($y + 1) * $cell - 1, $fg);
                    imagefilledrectangle($img, (4 - $x) * $cell, $y * $cell, (5 - $x) * $cell - 1, ($y + 1) * $cell - 1, $fg);
                }
            }
        }

        $folder = JPATH_ROOT . '/media/kunena/avatars/users';

        if (!is_dir($folder)) {
            KunenaFolder::create($folder);
        }

        $file = 'sample-' . preg_replace('/[^a-z0-9_-]/i', '-', $username) . '.png';
        $ok   = imagepng($img, $folder . '/' . $file);

        if (PHP_VERSION_ID < 80000) {
            imagedestroy($img);
        }

        return $ok ? 'users/' . $file : null;
    }

    /**
     * @param   array  $categories  Category key => id
     * @param   array  $users       User key => id
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function createModerators(array $categories, array $users)
    {
        $data = static::getData();

        foreach ($data['moderators'] as $userKey => $categoryKeys) {
            foreach ($categoryKeys as $categoryKey) {
                KunenaForumCategoryHelper::get($categories[$categoryKey])->addModerator(KunenaUserHelper::get($users[$userKey]));
            }
        }

        KunenaAccess::getInstance()->clearCache();
    }

    /**
     * @param   array    $users  User key => id
     * @param   integer  $now    Reference time
     *
     * @return  void
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function createAnnouncement(array $users, $now)
    {
        $data = static::getData();
        $def  = $data['announcement'];

        $announcement               = new KunenaForumAnnouncement();
        $announcement->title        = $def['title'];
        $announcement->sdescription = $def['sdescription'];
        $announcement->description  = $def['description'];
        $announcement->created_by   = $users[$def['by']];
        $announcement->created      = Factory::getDate($now - 3 * 86400)->toSql();
        $announcement->publish_up   = Factory::getDate($now - 3 * 86400)->toSql();
        $announcement->publish_down = Factory::getDbo()->getNullDate(); // no end: what Kunena's queries compare with
        $announcement->published    = 1;
        $announcement->showdate     = 1;
        $announcement->ordering     = 0;

        if (!$announcement->save()) {
            throw new RuntimeException('Announcement: ' . $announcement->getError());
        }

        static::track('announcement', $announcement->id);
    }

    /**
     * Creates one thread with its replies, poll, thank-yous and image attachments.
     *
     * @param   integer  $index  Thread number in the data set
     * @param   array    $state  Install state
     *
     * @return  void
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function createTopic($index, array &$state)
    {
        $data  = static::getData();
        $def   = $data['topics'][$index];
        $users = $state['users'];
        $keys  = array_keys($users);

        // Reproducible "random" choices per thread
        mt_srand(crc32('kunena-sampledata-' . $index));

        $hold     = isset($def['hold']) ? (int) $def['hold'] : KunenaForum::PUBLISHED;
        $category = KunenaForumCategoryHelper::get($state['categories'][$def['cat']]);
        $author   = KunenaUserHelper::get($users[$def['by']]);
        $replies  = (int) $def['replies'];

        // Timeline: sticky threads are older; long threads start earlier so their replies fit before "now"
        $age   = !empty($def['sticky']) ? mt_rand(150, 175) : mt_rand(5 + min($replies, 40) * 2, 140);
        $time  = $state['now'] - $age * 86400 - mt_rand(0, 86399);
        $step  = max(600, (int) (($state['now'] - 3600 - $time) / max(1, $replies + 1)));

        // The category is given explicitly: newTopic() would otherwise pick one the author may post in (locked ones excluded)
        list($topic, $message) = $category->newTopic(array('subject' => $def['subject'], 'message' => $def['body']), $author, array('category_id' => $category->id));
        $message->time = $time;
        $message->ip   = static::IP_PREFIX . mt_rand(10, 250);
        $message->hold = $hold;
        $topic->hold   = $hold;
        $topic->hits   = mt_rand(20, 80) + $replies * mt_rand(15, 40);

        if (!empty($def['sticky'])) {
            $topic->ordering = 1;
        }

        if (!empty($def['locked'])) {
            $topic->locked = 1;
        }

        if (!$message->save()) {
            throw new RuntimeException(sprintf('Thread "%s": %s', $def['subject'], $message->getError()));
        }

        static::track('topic', $message->thread);
        $topic = KunenaForumTopicHelper::get($message->thread, true);

        if (!empty($def['images'])) {
            for ($i = 1; $i <= (int) $def['images']; $i++) {
                static::createImageAttachment($message, $author, $index * 10 + $i);
            }
        }

        if (!empty($def['poll'])) {
            static::createPoll($topic, $def['poll'], $keys, $users);
        }

        $previous = $message;
        $names    = array($author->getName());

        for ($r = 1; $r <= $replies; $r++) {
            $state['replies']++;

            do {
                $replierKey = $keys[mt_rand(0, count($keys) - 1)];
            } while (count($keys) > 1 && $users[$replierKey] == $previous->userid);

            $replier = KunenaUserHelper::get($users[$replierKey]);
            $time    = min($state['now'] - 600 - $index * 97 - ($replies - $r) * 60, $time + mt_rand((int) ($step / 2), (int) ($step * 1.5)));

            // A few unapproved and trashed replies in live threads
            $replyHold = $hold;

            if ($hold === KunenaForum::PUBLISHED && $state['replies'] % 37 === 0) {
                $replyHold = KunenaForum::UNAPPROVED;
            } elseif ($hold === KunenaForum::PUBLISHED && $state['replies'] % 53 === 0) {
                $replyHold = KunenaForum::DELETED;
            }

            list(, $reply)  = $message->newReply(array('message' => static::replyText($def, $previous, $r)));
            $reply->userid  = $replier->userid;
            $reply->name    = $replier->getName('');
            $reply->time    = $time;
            $reply->ip      = static::IP_PREFIX . mt_rand(10, 250);
            $reply->hold    = $replyHold;
            $reply->subject = 'Re: ' . $def['subject'];

            if (!$reply->save()) {
                throw new RuntimeException(sprintf('Reply %d of "%s": %s', $r, $def['subject'], $reply->getError()));
            }

            // Photos in the photo thread
            if (isset($def['topic']) && $def['topic'] === 'photos' && $r % 4 === 0) {
                static::createImageAttachment($reply, $replier, $index * 100 + $r);
            }

            if ($replyHold === KunenaForum::PUBLISHED) {
                static::createThankyous($reply, $keys, $users);
                $previous = $reply;
            }
        }

        if ($hold === KunenaForum::PUBLISHED) {
            static::createThankyous($message, $keys, $users);
        }
    }

    /**
     * Builds a reply from the thread's reply pool.
     *
     * @param   array               $def       Thread definition
     * @param   KunenaForumMessage  $previous  Previous visible post
     * @param   integer             $number    Reply number
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function replyText(array $def, $previous, $number)
    {
        $data = static::getData();
        $pool = isset($def['topic']) ? $def['topic'] : (isset($data['category_pools'][$def['cat']]) ? $data['category_pools'][$def['cat']] : 'generic');
        $pool = array_merge($data['pools'][$pool], $pool === 'photos' ? array() : $data['pools']['generic']);
        // A reply doesn't repeat the post it follows
        for ($try = 0; $try < 10; $try++) {
            $text = $pool[mt_rand(0, count($pool) - 1)];

            if (strpos($previous->message, $text) === false) {
                break;
            }
        }

        if (mt_rand(1, 3) === 1) {
            $text = $data['openers'][mt_rand(0, count($data['openers']) - 1)] . ' ' . $text;
        }

        if (mt_rand(1, 4) === 1) {
            $text .= "\n\n" . $data['closers'][mt_rand(0, count($data['closers']) - 1)];
        }

        // Now and then, quote the previous post
        if ($number > 1 && mt_rand(1, 6) === 1) {
            $quoted = preg_split('/(?<=[.!?])\s/', trim(preg_replace('/\[[^\]]+\]/', '', $previous->message)), 2);
            $text   = '[quote="' . $previous->name . '" post=' . (int) $previous->id . ']' . $quoted[0] . "[/quote]\n" . $text;
        }

        // Unique, so Kunena's duplicate check never applies and every post differs a little
        return $text . (mt_rand(1, 5) === 1 ? ' :)' : '');
    }

    /**
     * @param   KunenaForumTopic  $topic    Thread
     * @param   array             $poll     [question, [options]]
     * @param   array             $keys     User keys
     * @param   array             $users    User key => id
     *
     * @return  void
     *
     * @throws  Exception
     * @since 5.3.0
     */
    protected static function createPoll($topic, array $poll, array $keys, array $users)
    {
        $kpoll        = $topic->getPoll();
        $kpoll->title = $poll[0];
        $kpoll->setOptions($poll[1]);

        if (!$kpoll->save()) {
            throw new RuntimeException('Poll: ' . $kpoll->getError());
        }

        static::track('poll', $kpoll->id);

        $topic->poll_id = $kpoll->id;
        $topic->save();

        $options = array_keys($kpoll->getOptions());

        if (!$options) {
            return;
        }

        foreach ($keys as $key) {
            if (mt_rand(1, 3) !== 1) {
                $kpoll->vote($options[mt_rand(0, count($options) - 1)], false, KunenaUserHelper::get($users[$key]));
            }
        }
    }

    /**
     * @param   KunenaForumMessage  $message  Post
     * @param   array               $keys     User keys
     * @param   array               $users    User key => id
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function createThankyous($message, array $keys, array $users)
    {
        if (mt_rand(1, 4) !== 1) {
            return;
        }

        KunenaForumMessageHelper::get($message->id, true);
        $thankyou = KunenaForumMessageThankyouHelper::get($message->id);
        $count    = mt_rand(1, 3);

        foreach (array_rand(array_flip($keys), min(count($keys), $count + 1)) as $key) {
            if ($count < 1 || $users[$key] == $message->userid) {
                continue;
            }

            try {
                $thankyou->save(KunenaUserHelper::get($users[$key]));
                $count--;
            } catch (Exception $e) {
                // Already thanked etc.: not important for sample data
            }
        }
    }

    /**
     * Attaches a generated landscape-like PNG image to a post.
     *
     * @param   KunenaForumMessage  $message  Post
     * @param   KunenaUser          $user     Author
     * @param   integer             $seed     Image variant
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function createImageAttachment($message, $user, $seed)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        mt_srand($seed);
        $width  = 960;
        $height = 640;
        $img    = imagecreatetruecolor($width, $height);

        // Sky gradient, hills and a sun
        for ($y = 0; $y < $height; $y++) {
            $t = $y / $height;
            $c = imagecolorallocate($img, (int) (120 + 80 * $t), (int) (170 + 50 * $t), (int) (230 - 20 * $t));
            imageline($img, 0, $y, $width, $y, $c);
        }

        imagefilledellipse($img, mt_rand(100, $width - 100), mt_rand(60, 180), 110, 110, imagecolorallocate($img, 255, 214, 90));

        for ($h = 0; $h < 3; $h++) {
            $green  = imagecolorallocate($img, 40 + $h * 20 + mt_rand(0, 20), 110 + $h * 25 + mt_rand(0, 20), 40 + mt_rand(0, 20));
            $points = array(0, $height);

            for ($x = 0; $x <= $width; $x += 80) {
                $points[] = $x;
                $points[] = (int) ($height * (0.45 + 0.15 * $h) + 40 * sin(($x + $seed * 37 + $h * 200) / 140));
            }

            $points[] = $width;
            $points[] = $height;

            // PHP 8.1+: imagefilledpolygon() without the deprecated number-of-points argument
            if (PHP_VERSION_ID >= 80000) {
                imagefilledpolygon($img, $points, $green);
            } else {
                imagefilledpolygon($img, $points, count($points) / 2, $green);
            }
        }

        $folder = 'media/kunena/attachments/' . (int) $user->userid;

        if (!is_dir(JPATH_ROOT . '/' . $folder)) {
            KunenaFolder::create(JPATH_ROOT . '/' . $folder);
        }

        $name = 'sample-photo-' . (int) $seed . '-' . substr(md5(uniqid('', true)), 0, 8) . '.png';
        $path = JPATH_ROOT . '/' . $folder . '/' . $name;

        if (!imagepng($img, $path)) {
            return;
        }

        if (PHP_VERSION_ID < 80000) {
            imagedestroy($img);
        }

        KunenaForumMessageHelper::get($message->id, true);

        $attachment                = new KunenaAttachment();
        $attachment->mesid         = $message->id;
        $attachment->userid        = $user->userid;
        $attachment->protected     = 0;
        $attachment->hash          = md5_file($path);
        $attachment->size          = filesize($path);
        $attachment->folder        = $folder;
        $attachment->filetype      = 'image/png';
        $attachment->filename      = $name;
        $attachment->filename_real = 'garden-' . (int) $seed . '.png';
        $attachment->caption       = '';
        $attachment->inline        = 0;

        if (!$attachment->save()) {
            @unlink($path);
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Removal
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Removes a sample thread. Posts by other users are kept, with the thread.
     *
     * @param   integer  $topicId      Thread ID
     * @param   array    $sampleUsers  Sample user IDs
     * @param   array    $state        Removal state
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function removeTopic($topicId, array $sampleUsers, array &$state)
    {
        $topic = KunenaForumTopicHelper::get($topicId, true);

        if (!$topic->exists()) {
            return;
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'userid')))
                ->from($db->quoteName('#__kunena_messages'))
                ->where($db->quoteName('thread') . ' = ' . (int) $topicId)
        );
        $messages = (array) $db->loadObjectList();
        $foreign  = array_filter($messages, function ($m) use ($sampleUsers) {
            return !in_array((int) $m->userid, $sampleUsers, true);
        });

        if (!$foreign) {
            KunenaForumTrash::purgeTopic($topic);

            return;
        }

        // Other users posted here: keep their posts, remove only the sample ones.
        foreach ($messages as $m) {
            if (in_array((int) $m->userid, $sampleUsers, true)) {
                KunenaForumTrash::purgeMessage(KunenaForumMessageHelper::get($m->id, true));
            }
        }

        KunenaForumTopicHelper::recount(array((int) $topicId));
        $state['kept'][] = sprintf('Thread "%s" (ID %d) was kept: other users posted in it.', $topic->subject, $topicId);
    }

    /**
     * Polls whose thread is gone already go with it; this removes any left behind (e.g. by an interrupted installation).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function removePolls()
    {
        $db = Factory::getDbo();

        foreach (static::getTracked('poll') as $id) {
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__kunena_topics') . ' WHERE ' . $db->quoteName('poll_id') . ' = ' . (int) $id);

            // Still used by a thread which was kept (other users posted in it): keep the poll too
            if (!(int) $db->loadResult()) {
                foreach (array('#__kunena_polls_users' => 'pollid', '#__kunena_polls_options' => 'pollid', '#__kunena_polls' => 'id') as $table => $column) {
                    $db->setQuery('DELETE FROM ' . $db->quoteName($table) . ' WHERE ' . $db->quoteName($column) . ' = ' . (int) $id)->execute();
                }
            }

            static::untrack('poll', $id);
        }
    }

    /**
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function removeAnnouncements()
    {
        foreach (static::getTracked('announcement') as $id) {
            $announcement = KunenaForumAnnouncementHelper::get($id);

            if ($announcement->exists()) {
                $announcement->delete();
            }

            static::untrack('announcement', $id);
        }
    }

    /**
     * Deletes the sample categories which are empty now (children first); keeps the others.
     *
     * @param   array  $state  Removal state
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function removeCategories(array &$state)
    {
        $db  = Factory::getDbo();
        $ids = static::getTracked('category');

        // Children first: highest IDs were created last (sections before their categories)
        rsort($ids);

        foreach ($ids as $id) {
            $category = KunenaForumCategoryHelper::get($id);

            if (!$category->exists()) {
                static::untrack('category', $id);
                continue;
            }

            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__kunena_topics') . ' WHERE ' . $db->quoteName('category_id') . ' = ' . (int) $id);
            $topics = (int) $db->loadResult();
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__kunena_categories') . ' WHERE ' . $db->quoteName('parent_id') . ' = ' . (int) $id);
            $children = (int) $db->loadResult();

            if ($topics || $children) {
                $state['kept'][] = sprintf('Category "%s" (ID %d) was kept: it contains other threads or categories.', $category->name, $id);
            } else {
                $category->delete();
            }

            static::untrack('category', $id);
        }

        KunenaAccess::getInstance()->clearCache();
    }

    /**
     * Deletes the sample users (Joomla accounts, Kunena profiles, avatars).
     *
     * @param   array  $ids  User IDs
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function removeUsers(array $ids)
    {
        $db = Factory::getDbo();

        foreach ($ids as $id) {
            $kuser = KunenaUserHelper::get($id);

            // Only avatars generated by the sample data, with the resized copies Kunena made of them
            if ($kuser->avatar && strpos($kuser->avatar, 'users/sample-') === 0) {
                $files = array_merge(
                    array(JPATH_ROOT . '/media/kunena/avatars/' . $kuser->avatar),
                    (array) glob(JPATH_ROOT . '/media/kunena/avatars/resized/*/' . $kuser->avatar)
                );

                foreach ($files as $file) {
                    if ($file && is_file($file)) {
                        KunenaFile::delete($file);
                    }
                }
            }

            foreach (array('#__kunena_users' => 'userid', '#__kunena_user_categories' => 'user_id', '#__kunena_user_topics' => 'user_id',
                         '#__kunena_user_read' => 'user_id', '#__kunena_sessions' => 'userid', '#__kunena_thankyou' => 'userid',
                         '#__kunena_polls_users' => 'userid', ) as $table => $column) {
                $db->setQuery('DELETE FROM ' . $db->quoteName($table) . ' WHERE ' . $db->quoteName($column) . ' = ' . (int) $id)->execute();
            }

            $user = new User();

            if ($user->load($id)) {
                $user->delete();
            }

            static::untrack('user', $id);
        }
    }

    // -----------------------------------------------------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------------------------------------------------

    /**
     * Updates all statistics.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function recount()
    {
        KunenaForumTopicHelper::recount();
        KunenaForumTopicUserHelper::recount();
        KunenaForumTrash::finish();
    }

    /**
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function clearCaches()
    {
        KunenaAccess::getInstance()->clearCache();

        try {
            Factory::getCache()->clean('com_kunena');
        } catch (Exception $e) {
            // Not important
        }
    }

    /**
     * @return  boolean
     *
     * @since 5.3.0
     */
    protected static function tableExists()
    {
        $db = Factory::getDbo();

        return in_array($db->replacePrefix(static::TABLE), (array) $db->getTableList(), true);
    }

    /**
     * Creates the tracking table (portable SQL: MySQL, PostgreSQL, SQLite).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function ensureTable()
    {
        if (static::tableExists()) {
            return;
        }

        $db = Factory::getDbo();
        $db->setQuery(
            'CREATE TABLE IF NOT EXISTS ' . $db->quoteName(static::TABLE) . ' ('
            . $db->quoteName('item_type') . ' VARCHAR(20) NOT NULL, '
            . $db->quoteName('item_id') . ' INTEGER NOT NULL, '
            . 'PRIMARY KEY (' . $db->quoteName('item_type') . ', ' . $db->quoteName('item_id') . '))'
        )->execute();
    }

    /**
     * @param   string   $type  Item type
     * @param   integer  $id    Item ID
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function track($type, $id)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->insert($db->quoteName(static::TABLE))
                ->columns($db->quoteName(array('item_type', 'item_id')))
                ->values($db->quote($type) . ', ' . (int) $id)
        )->execute();
    }

    /**
     * @param   string   $type  Item type
     * @param   integer  $id    Item ID
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function untrack($type, $id)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName(static::TABLE))
                ->where($db->quoteName('item_type') . ' = ' . $db->quote($type))
                ->where($db->quoteName('item_id') . ' = ' . (int) $id)
        )->execute();
    }

    /**
     * @param   string  $type  Item type
     *
     * @return  array  IDs
     *
     * @since 5.3.0
     */
    protected static function getTracked($type)
    {
        if (!static::tableExists()) {
            return array();
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('item_id'))
                ->from($db->quoteName(static::TABLE))
                ->where($db->quoteName('item_type') . ' = ' . $db->quote($type))
                ->order($db->quoteName('item_id'))
        );

        return array_map('intval', (array) $db->loadColumn());
    }
}

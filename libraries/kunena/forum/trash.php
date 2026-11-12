<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Forum.Trash
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;

/**
 * Empties Kunena's trash: permanently deletes every trashed (deleted) thread and every trashed post of the threads
 * which are still live, with their attachments (files included), polls, thank-yous, ratings and read/subscription
 * records, then updates the statistics.
 *
 * The work is done in batches limited by time, so that large trashes can be emptied over several requests
 * (administrator Tools > Empty Trash) or in one go (command line: kunena:trash:purge).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaForumTrash
{
    /**
     * Number of items loaded per database round trip.
     *
     * @since 5.3.0
     */
    public const BATCH_SIZE = 50;

    /**
     * Trashed threads: deleted (2) and deleted together with their topic (3).
     *
     * @var array
     * @since 5.3.0
     */
    protected static $deletedStates = array(KunenaForum::DELETED, KunenaForum::TOPIC_DELETED);

    /**
     * Counts what is in the trash.
     *
     * @return  array  ['topics' => trashed threads, 'messages' => trashed posts of live threads]
     *
     * @since 5.3.0
     */
    public static function count()
    {
        $db = Factory::getDbo();

        $db->setQuery(static::getTopicsQuery()->clear('select')->clear('order')->select('COUNT(*)'));
        $topics = (int) $db->loadResult();

        $db->setQuery(static::getMessagesQuery()->clear('select')->clear('order')->select('COUNT(*)'));
        $messages = (int) $db->loadResult();

        return array('topics' => $topics, 'messages' => $messages);
    }

    /**
     * Permanently deletes trashed threads and posts until the trash is empty or the time limit is reached.
     *
     * @param   integer  $timeLimit  Stop starting new items after this many seconds (0: no limit).
     * @param   array    $skip       IDs which failed before (['topics' => [...], 'messages' => [...]]); updated.
     *
     * @return  array  ['topics' => deleted threads, 'messages' => deleted posts, 'affected' => live thread IDs,
     *                  'errors' => messages, 'done' => true when nothing is left]
     *
     * @since 5.3.0
     */
    public static function purge($timeLimit = 0, array &$skip = array())
    {
        $start  = microtime(true);
        $result = array('topics' => 0, 'messages' => 0, 'affected' => array(), 'errors' => array(), 'done' => false);

        $skip += array('topics' => array(), 'messages' => array());

        // 1. Trashed threads, with all their posts
        while (true) {
            $ids = static::loadIds(static::getTopicsQuery($skip['topics']));

            if (!$ids) {
                break;
            }

            foreach (KunenaForumTopicHelper::getTopics($ids, 'none') as $topic) {
                if (static::purgeTopic($topic)) {
                    $result['topics']++;
                } else {
                    $skip['topics'][]   = (int) $topic->id;
                    $result['errors'][] = sprintf('Thread %d: %s', $topic->id, $topic->getError() ?: 'could not be deleted');
                }

                if (static::timeUp($start, $timeLimit)) {
                    return $result;
                }
            }
        }

        // 2. Trashed posts of live threads
        while (true) {
            $ids = static::loadIds(static::getMessagesQuery($skip['messages']));

            if (!$ids) {
                break;
            }

            foreach (KunenaForumMessageHelper::getMessages($ids, 'none') as $message) {
                $topicId = (int) $message->thread;

                if (static::purgeMessage($message)) {
                    $result['messages']++;
                    $result['affected'][$topicId] = $topicId;
                } else {
                    $skip['messages'][] = (int) $message->id;
                    $result['errors'][] = sprintf('Post %d: %s', $message->id, $message->getError() ?: 'could not be deleted');
                }

                if (static::timeUp($start, $timeLimit)) {
                    return $result;
                }
            }
        }

        $result['done'] = true;

        return $result;
    }

    /**
     * Updates the statistics after purging: the live threads which lost posts, categories, users, thank-yous,
     * polls, and removes attachments left without a post.
     *
     * @param   array  $affected  IDs of live threads which lost posts.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function finish(array $affected = array())
    {
        $affected = array_values(array_filter(ArrayHelper::toInteger($affected)));

        if ($affected) {
            foreach (array_chunk($affected, 500) as $chunk) {
                KunenaForumTopicHelper::recount($chunk);
                KunenaForumTopicUserHelper::recount($chunk);
            }
        }

        KunenaForumCategoryHelper::recount();
        KunenaForumMessageThankyouHelper::recount();
        KunenaUserHelper::recount();
        KunenaForumTopicPollHelper::recount();

        // Attachments whose post no longer exists (KunenaAttachmentHelper::cleanup() handles 50 per call)
        $db = Factory::getDbo();

        for ($i = 0; $i < 1000; $i++) {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__kunena_attachments', 'a'))
                    ->leftJoin($db->quoteName('#__kunena_messages', 'm') . ' ON ' . $db->quoteName('m.id') . ' = ' . $db->quoteName('a.mesid'))
                    ->where($db->quoteName('m.id') . ' IS NULL')
                    ->where($db->quoteName('a.mesid') . ' > 0')
            );

            if (!(int) $db->loadResult() || !KunenaAttachmentHelper::cleanup()) {
                break;
            }
        }

        try {
            Factory::getCache()->clean('com_kunena');
        } catch (Exception $e) {
            // A cache which can't be cleaned doesn't change the result.
        }
    }

    /**
     * Permanently deletes a thread with all its posts and their attachments.
     *
     * @param   KunenaForumTopic  $topic  Thread
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function purgeTopic(KunenaForumTopic $topic)
    {
        $db = Factory::getDbo();

        // KunenaForumTopic::delete() removes the posts, but not their attachments.
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__kunena_messages'))
                ->where($db->quoteName('thread') . ' = ' . (int) $topic->id)
        );
        $messageIds = array_map('intval', (array) $db->loadColumn());

        foreach (array_chunk($messageIds, 200) as $chunk) {
            static::deleteAttachments($chunk);
        }

        // Statistics are updated once, by finish().
        return (bool) $topic->delete(false);
    }

    /**
     * Permanently deletes a post with its attachments.
     *
     * @param   KunenaForumMessage  $message  Post
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function purgeMessage(KunenaForumMessage $message)
    {
        // KunenaForumMessage::delete() only finds the attachments the current user may read.
        static::deleteAttachments(array((int) $message->id));

        // Statistics are updated once, by finish().
        return (bool) $message->delete(false);
    }

    /**
     * Deletes the attachments (records and files) of the given posts.
     *
     * @param   array  $messageIds  Post IDs
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function deleteAttachments(array $messageIds)
    {
        if (!$messageIds) {
            return;
        }

        foreach (KunenaAttachmentHelper::getByMessage($messageIds, 'none') as $attachment) {
            // The files are removed when the deleted attachment object is destroyed.
            $attachment->delete();
        }
    }

    /**
     * Query for trashed threads.
     *
     * @param   array  $skip  Thread IDs to leave out.
     *
     * @return  JDatabaseQuery
     *
     * @since 5.3.0
     */
    protected static function getTopicsQuery(array $skip = array())
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('t.id'))
            ->from($db->quoteName('#__kunena_topics', 't'))
            ->where($db->quoteName('t.hold') . ' IN (' . implode(',', array_map('intval', static::$deletedStates)) . ')')
            ->order($db->quoteName('t.id'));

        if ($skip) {
            $query->where($db->quoteName('t.id') . ' NOT IN (' . implode(',', array_map('intval', $skip)) . ')');
        }

        return $query;
    }

    /**
     * Query for trashed posts of live (published or unapproved) threads. The posts of trashed threads go with
     * their thread.
     *
     * @param   array  $skip  Post IDs to leave out.
     *
     * @return  JDatabaseQuery
     *
     * @since 5.3.0
     */
    protected static function getMessagesQuery(array $skip = array())
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('m.id'))
            ->from($db->quoteName('#__kunena_messages', 'm'))
            ->innerJoin($db->quoteName('#__kunena_topics', 't') . ' ON ' . $db->quoteName('t.id') . ' = ' . $db->quoteName('m.thread'))
            ->where($db->quoteName('m.hold') . ' IN (' . implode(',', array_map('intval', static::$deletedStates)) . ')')
            ->where($db->quoteName('t.hold') . ' IN (' . (int) KunenaForum::PUBLISHED . ',' . (int) KunenaForum::UNAPPROVED . ')')
            ->order($db->quoteName('m.id'));

        if ($skip) {
            $query->where($db->quoteName('m.id') . ' NOT IN (' . implode(',', array_map('intval', $skip)) . ')');
        }

        return $query;
    }

    /**
     * Loads the next batch of IDs.
     *
     * @param   JDatabaseQuery  $query  Query
     *
     * @return  array
     *
     * @since 5.3.0
     */
    protected static function loadIds($query)
    {
        $db = Factory::getDbo();
        $db->setQuery($query, 0, static::BATCH_SIZE);

        return array_map('intval', (array) $db->loadColumn());
    }

    /**
     * Is the time limit reached?
     *
     * @param   float    $start      Start time
     * @param   integer  $timeLimit  Seconds (0: no limit)
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    protected static function timeUp($start, $timeLimit)
    {
        return $timeLimit > 0 && (microtime(true) - $start) >= $timeLimit;
    }
}

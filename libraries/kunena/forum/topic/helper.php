<?php

/**
 * Kunena Component
 * @package       Kunena.Framework
 * @subpackage    Forum.Topic
 *
 * @copyright     Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\Utilities\ArrayHelper;

/**
 * Class KunenaForumTopicHelper
 * @since Kunena
 */
#[\AllowDynamicProperties]
abstract class KunenaForumTopicHelper
{
    /**
     * @var KunenaForumTopic[]
     * @since Kunena
     */
    protected static $_instances = array();

    /**
     * Returns KunenaForumTopic object.
     *
     * @param   int  $identifier The topic to load - Can be only an integer.
     * @param   bool $reload     reload
     *
     * @return KunenaForumTopic
     * @since Kunena
     */
    public static function get($identifier = null, $reload = false)
    {
        if ($identifier instanceof KunenaForumTopic) {
            return $identifier;
        }

        $id = (int) $identifier;

        if ($id < 1) {
            return new KunenaForumTopic();
        }

        if (empty(self::$_instances[$id])) {
            $instance = new KunenaForumTopic();

            // Only load topics which haven't been preloaded before (including missing ones).
            $instance->load(!array_key_exists($id, self::$_instances) ? $id : null);
            $instance->id          = $id;
            self::$_instances[$id] = $instance;
        } elseif ($reload) {
            self::$_instances[$id]->load();
        }

        return self::$_instances[$id];
    }

    /**
     * @param   mixed $ids   ids
     * @param   bool  $value value
     * @param   mixed $user  user
     *
     * @return integer
     * @throws Exception
     * @since Kunena
     */
    public static function subscribe($ids, $value = true, $user = null)
    {
        // Pre-load all items
        KunenaForumTopicUserHelper::getTopics($ids, $user);
        $count = 0;

        foreach ($ids as $id) {
            $usertopic = KunenaForumTopicUserHelper::get($id, $user);

            if ($usertopic->subscribed != (int) $value) {
                $count++;
            }

            $usertopic->subscribed = (int) $value;
            $usertopic->save();
        }

        return $count;
    }

    /**
     * @param   mixed $ids   ids
     * @param   bool  $value value
     * @param   mixed $user  user
     *
     * @return integer
     * @throws Exception
     * @since Kunena
     */
    public static function favorite($ids, $value = true, $user = null)
    {
        // Pre-load all items
        KunenaForumTopicUserHelper::getTopics($ids, $user);
        $count = 0;

        foreach ($ids as $id) {
            $usertopic = KunenaForumTopicUserHelper::get($id, $user);

            if ($usertopic->favorite != (int) $value) {
                $count++;
            }

            $usertopic->favorite = (int) $value;
            $usertopic->save();
        }

        return $count;
    }

    /**
     * @param   mixed  $ids       ids
     * @param   string $authorise authorise
     *
     * @return KunenaForumTopic[]
     * @throws Exception
     * @throws null
     * @since Kunena
     */
    public static function getTopics($ids = false, $authorise = 'read')
    {
        KUNENA_PROFILER ? KunenaProfiler::instance()->start('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

        if ($ids === false) {
            return self::$_instances;
        } elseif (is_array($ids)) {
            $ids = array_unique($ids);
        } else {
            $ids = array($ids);
        }

        self::loadTopics($ids);

        $list = array();

        foreach ($ids as $id) {
            if (!empty(self::$_instances [$id]) && self::$_instances [$id]->isAuthorised($authorise, null)) {
                $list [$id] = self::$_instances [$id];
            }
        }

        KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

        return $list;
    }

    /**
     * @param   array $ids ids
     *
     * @throws Exception
     * @since Kunena
     * @return void
     */
    protected static function loadTopics(array $ids)
    {
        foreach ($ids as $i => $id) {
            // Security: cast to integer, the raw value was used in the query below.
            $id      = intval($id);
            $ids[$i] = $id;

            if (!$id || isset(self::$_instances [$id])) {
                unset($ids[$i]);
            }
        }

        if (empty($ids)) {
            return;
        }

        $idlist = implode(',', $ids);
        $db     = Factory::getDBO();
        $query  = "SELECT * FROM #__kunena_topics WHERE id IN ({$idlist})";
        $db->setQuery($query);

        try {
            $results = (array) $db->loadAssocList('id');
        } catch (JDatabaseExceptionExecuting $e) {
            KunenaError::displayDatabaseError($e);
        }

        foreach ($ids as $id) {
            if (isset($results[$id])) {
                $instance = new KunenaForumTopic($results[$id]);
                $instance->exists(true);
                self::$_instances [$id] = $instance;
            } else {
                self::$_instances [$id] = null;
            }
        }

        unset($results);
    }

    /**
     * @param   mixed $ids  ids
     * @param   mixed $user user
     *
     * @return KunenaForumTopicUser[]
     * @throws Exception
     * @since Kunena
     */
    public static function getUserTopics($ids = false, $user = null)
    {
        if ($ids === false) {
            $ids = array_keys(self::$_instances);
        }

        return KunenaForumTopicUserHelper::getTopics($ids, $user);
    }

    /**
     * @param   mixed $categories categories
     * @param   int   $limitstart limitstart
     * @param   int   $limit      limit
     * @param   array $params     params
     *
     * @return array|KunenaForumTopic[]
     * @throws Exception
     * @throws null
     * @since Kunena
     */
    public static function getLatestTopics($categories = false, $limitstart = 0, $limit = 0, $params = array())
    {
        KUNENA_PROFILER ? KunenaProfiler::instance()->start('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;
        $db     = Factory::getDBO();
        $config = KunenaFactory::getConfig();

        if ($limit < 1 && empty($params['nolimit'])) {
            $limit = $config->threads_per_page;
        }

        $reverse   = isset($params['reverse']) ? (int) $params['reverse'] : 0;
        $exclude   = isset($params['exclude']) ? (int) $params['exclude'] : 0;
        $orderby   = isset($params['orderby']) ? (string) $params['orderby'] : 'tt.last_post_time DESC';
        $starttime = isset($params['starttime']) ? (int) $params['starttime'] : 0;
        $user      = isset($params['user']) ? KunenaUserHelper::get($params['user']) : KunenaUserHelper::getMyself();
        $hold      = isset($params['hold']) ? (string) $params['hold'] : 0;
        $moved     = isset($params['moved']) ? (string) $params['moved'] : 0;

        // Security: hold is used inside IN (), allow only a list of integers.
        $hold = implode(',', ArrayHelper::toInteger(explode(',', (string) $hold))) ?: '0';
        $where     = isset($params['where']) ? (string) $params['where'] : '';

        if (strstr('ut.last_', $orderby)) {
            $post_time_field = 'ut.last_post_time';
        } elseif (strstr('tt.first_', $orderby)) {
            $post_time_field = 'tt.first_post_time';
        } else {
            $post_time_field = 'tt.last_post_time';
        }

        if (!$exclude) {
            $categories = KunenaForumCategoryHelper::getCategories($categories, $reverse);
        } else {
            // Security: "NOT IN (excluded)" also matched categories the user cannot read,
            // select all readable categories except the excluded ones instead.
            $categories = KunenaForumCategoryHelper::getCategories($categories, 1);
            $exclude    = 0;
        }

        $catlist = array();

        foreach ($categories as $category) {
            $catlist += $category->getChannels();
        }

        if (empty($catlist)) {
            KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

            return array(0, array());
        }

        // Security: unapproved/deleted topics may only be listed from categories (incl. channels) where the
        // current user is allowed to see them, not from all channels of a category he moderates.
        $holdWhere = "tt.hold IN ({$hold})";
        $holdList  = array_unique(ArrayHelper::toInteger(explode(',', $hold)));

        if (array_diff($holdList, array(0))) {
            $access    = KunenaAccess::getInstance();
            $me        = KunenaUserHelper::getMyself();
            $holdParts = array();

            foreach ($holdList as $holdValue) {
                if ($holdValue == 0) {
                    $holdParts[] = 'tt.hold=0';
                    continue;
                }

                $holdCats = array();

                foreach (array_keys($catlist) as $holdCatid) {
                    if (in_array($holdValue, $access->getAllowedHold($me, $holdCatid, false))) {
                        $holdCats[] = (int) $holdCatid;
                    }
                }

                if ($holdCats) {
                    $holdParts[] = "(tt.hold={$holdValue} AND tt.category_id IN (" . implode(',', $holdCats) . '))';
                }
            }

            $holdWhere = $holdParts ? '(' . implode(' OR ', $holdParts) . ')' : '0';
        }

        $catlist = implode(',', array_keys($catlist));

        $whereuser = array();

        if (!empty($params['started'])) {
            $whereuser[] = 'ut.owner=1';
        }

        if (!empty($params['replied'])) {
            $whereuser[] = '(ut.owner=0 AND ut.posts>0)';
        }

        if (!empty($params['posted'])) {
            $whereuser[] = 'ut.posts>0';
        }

        if (!empty($params['favorited'])) {
            $whereuser[] = 'ut.favorite=1';
        }

        if (!empty($params['subscribed'])) {
            $whereuser[] = 'ut.subscribed=1';
        }



        $wheretime = ($starttime ? " AND {$post_time_field}>{$db->Quote($starttime)}" : '');
        $whereuser = ($whereuser ? " AND ut.user_id={$db->Quote($user->userid)} AND (" . implode(' OR ', $whereuser) . ')' : '');

        if ($exclude) {
            $where = "{$holdWhere} AND tt.category_id NOT IN ({$catlist}) {$whereuser} {$wheretime} {$where}";
        } else {
            $where = "{$holdWhere} AND tt.category_id IN ({$catlist}) {$whereuser} {$wheretime} {$where}";
        }

        if (!$moved) {
            $where .= " AND tt.moved_id='0'";
        }

        // Get total count
        if ($whereuser) {
            $query = "SELECT COUNT(*) FROM #__kunena_user_topics AS ut INNER JOIN #__kunena_topics AS tt ON tt.id=ut.topic_id WHERE {$where}";
        } else {
            $query = "SELECT COUNT(*) FROM #__kunena_topics AS tt WHERE {$where}";
        }

        $db->setQuery($query);

        try {
            $total = (int) $db->loadResult();
        } catch (JDatabaseExceptionExecuting $e) {
            KunenaError::displayDatabaseError($e);

            return array(0, array());
        }

        if (!$total) {
            KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

            return array(0, array());
        }

        // If out of range, use last page
        if ($limit && $total < $limitstart) {
            $limitstart = intval($total / $limit) * $limit;
        }

        // Get items
        if ($whereuser) {
            $query = "SELECT tt.*, ut.posts AS myposts, ut.last_post_id AS my_last_post_id, ut.favorite, tt.last_post_id AS lastread, 0 AS unread
				FROM #__kunena_user_topics AS ut
				INNER JOIN #__kunena_topics AS tt ON tt.id=ut.topic_id
				WHERE {$where} ORDER BY {$orderby}";
        } else {
            $query = "SELECT tt.*, ut.posts AS myposts, ut.last_post_id AS my_last_post_id, ut.favorite, tt.last_post_id AS lastread, 0 AS unread
				FROM #__kunena_topics AS tt
				LEFT JOIN #__kunena_user_topics AS ut ON tt.id=ut.topic_id AND ut.user_id={$db->Quote($user->userid)}
				WHERE {$where} ORDER BY {$orderby}";
        }

        $db->setQuery($query, $limitstart, $limit);

        try {
            $results = (array) $db->loadAssocList('id');
        } catch (JDatabaseExceptionExecuting $e) {
            KunenaError::displayDatabaseError($e);

            KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

            return array(0, array());
        }

        $topics = array();

        foreach ($results as $id => $result) {
            $instance = new KunenaForumTopic($result);
            $instance->exists(true);
            self::$_instances [$id] = $instance;
            $topics[$id]            = $instance;
        }

        unset($results);
        KUNENA_PROFILER ? KunenaProfiler::instance()->stop('function ' . __CLASS__ . '::' . __FUNCTION__ . '()') : null;

        return array($total, $topics);
    }

    /**
     * Method to delete selected topics.
     *
     * @param   array|int $ids ids
     *
     * @return integer    Count of deleted topics.
     * @throws Exception
     * @since Kunena
     */
    public static function delete($ids)
    {
        if (empty($ids)) {
            return 0;
        }

        if (is_array($ids)) {
            // Security: cast to integer.
            $idlist = implode(',', ArrayHelper::toInteger($ids)) ?: '0';
        } else {
            $idlist = (int) $ids;
        }

        // Delete user topics
        $queries[] = "DELETE FROM #__kunena_user_topics WHERE topic_id IN ({$idlist})";

        // Delete user read
        $queries[] = "DELETE FROM #__kunena_user_read WHERE topic_id IN ({$idlist})";

        if (!KunenaDatabaseHelper::isMysql()) {
            // Same as below, without MySQL's multiple-table DELETE (PostgreSQL, SQLite)
            $queries[] = "DELETE FROM #__kunena_thankyou WHERE postid IN (SELECT m.id FROM #__kunena_messages AS m WHERE m.thread IN ({$idlist}))";
            $queries[] = "DELETE FROM #__kunena_polls_users WHERE pollid IN (SELECT tt.poll_id FROM #__kunena_topics AS tt WHERE tt.id IN ({$idlist}) AND tt.moved_id=0)";
            $queries[] = "DELETE FROM #__kunena_polls_options WHERE pollid IN (SELECT tt.poll_id FROM #__kunena_topics AS tt WHERE tt.id IN ({$idlist}) AND tt.moved_id=0)";
            $queries[] = "DELETE FROM #__kunena_polls WHERE id IN (SELECT tt.poll_id FROM #__kunena_topics AS tt WHERE tt.id IN ({$idlist}) AND tt.moved_id=0)";
            $queries[] = "DELETE FROM #__kunena_messages_text WHERE mesid IN (SELECT m.id FROM #__kunena_messages AS m WHERE m.thread IN ({$idlist}))";
            $queries[] = "DELETE FROM #__kunena_messages WHERE thread IN ({$idlist})";
        } else {
            // Delete thank yous
            $queries[] = "DELETE t FROM #__kunena_thankyou AS t INNER JOIN #__kunena_messages AS m ON m.id=t.postid WHERE m.thread IN ({$idlist})";

            // Delete poll users (if not shadow)
            $queries[] = "DELETE p FROM #__kunena_polls_users AS p INNER JOIN #__kunena_topics AS tt ON tt.poll_id=p.pollid WHERE tt.id IN ({$idlist}) AND tt.moved_id=0";

            // Delete poll options (if not shadow)
            $queries[] = "DELETE p FROM #__kunena_polls_options AS p INNER JOIN #__kunena_topics AS tt ON tt.poll_id=p.pollid WHERE tt.id IN ({$idlist}) AND tt.moved_id=0";

            // Delete polls (if not shadow)
            $queries[] = "DELETE p FROM #__kunena_polls AS p INNER JOIN #__kunena_topics AS tt ON tt.poll_id=p.id WHERE tt.id IN ({$idlist}) AND tt.moved_id=0";

            // Delete messages
            $queries[] = "DELETE m, t FROM #__kunena_messages AS m INNER JOIN #__kunena_messages_text AS t ON m.id=t.mesid WHERE m.thread IN ({$idlist})";
        }

        // TODO: delete attachments
        // Delete topics
        $queries[] = "DELETE FROM #__kunena_topics WHERE id IN ({$idlist})";

        $db = Factory::getDBO();

        foreach ($queries as $query) {
            $db->setQuery($query);

            try {
                $db->execute();
            } catch (JDatabaseExceptionExecuting $e) {
                KunenaError::displayDatabaseError($e);

                return false;
            }
        }

        return $db->getAffectedRows();
    }

    /**
     * Method to trash topics. They will be marked as deleted, but still exist in database.
     *
     * @param   array|int $ids ids
     *
     * @return integer    Count of trashed topics.
     * @throws Exception
     * @since Kunena
     */
    public static function trash($ids)
    {
        if (empty($ids)) {
            return 0;
        }

        if (is_array($ids)) {
            // Security: cast to integer.
            $idlist = implode(',', ArrayHelper::toInteger($ids)) ?: '0';
        } else {
            $idlist = (int) $ids;
        }

        $db        = Factory::getDBO();
        $queries[] = "UPDATE #__kunena_messages SET hold='2' WHERE thread IN ({$idlist})";
        $queries[] = "UPDATE #__kunena_topics SET hold='2' WHERE id IN ({$idlist})";

        foreach ($queries as $query) {
            $db->setQuery($query);

            try {
                $db->execute();
            } catch (JDatabaseExceptionExecuting $e) {
                KunenaError::displayDatabaseError($e);

                return false;
            }
        }

        return $db->getAffectedRows();
    }

    /**
     * Free up memory by cleaning up all cached items.
     * @since Kunena
     * @return void
     */
    public static function cleanup()
    {
        self::$_instances = array();
    }

    /**
     * @param   mixed $ids   ids
     * @param   int   $start start
     * @param   int   $end   end
     *
     * @return boolean|integer
     * @throws Exception
     * @since Kunena
     */
    public static function recount($ids = false, $start = 0, $end = 0)
    {
        $db = Factory::getDBO();

        if (is_array($ids)) {
            $threads = 'AND m.thread IN (' . (implode(',', ArrayHelper::toInteger($ids)) ?: '0') . ')';
        } elseif ((int) $ids) {
            $threads = 'AND m.thread=' . (int) $ids;
        } else {
            $threads = '';
        }

        if ($end) {
            $start = (int) $start;
            $end   = (int) $end;

            if ($start < 1) {
                $start = 1;
            }

            $topics = " AND (tt.id BETWEEN {$start} AND {$end})";

            // 5.3.0: the posts' aggregations read only the batch's threads (they used to aggregate every post of the
            // forum for every batch, leaving it to the database to discard the other threads)
            $range = " AND (m.thread BETWEEN {$start} AND {$end})";
        } else {
            $topics = '';
            $range  = '';
        }

        $postgresql = KunenaDatabaseHelper::isPostgresql($db);

        // Mark all empty topics as deleted
        $query = $postgresql ? "UPDATE #__kunena_topics AS tt
			SET hold = 4,
				posts = 0,
				attachments = 0,
				first_post_id = 0,
				first_post_time = 0,
				first_post_userid = 0,
				first_post_message = '',
				first_post_guest_name = '',
				last_post_id = 0,
				last_post_time = 0,
				last_post_userid = 0,
				last_post_message = '',
				last_post_guest_name = ''
			WHERE tt.moved_id=0 AND tt.hold!=4 AND NOT EXISTS (SELECT 1 FROM #__kunena_messages AS m WHERE m.thread=tt.id AND tt.hold=m.hold) {$topics} "
            . str_replace('m.thread', 'tt.id', $threads) : "UPDATE #__kunena_topics AS tt
			LEFT JOIN #__kunena_messages AS m ON m.thread=tt.id AND tt.hold=m.hold
			SET tt.hold = 4,
				tt.posts = 0,
				tt.attachments = 0,
				tt.first_post_id = 0,
				tt.first_post_time = 0,
				tt.first_post_userid = 0,
				tt.first_post_message = '',
				tt.first_post_guest_name = '',
				tt.last_post_id = 0,
				tt.last_post_time = 0,
				tt.last_post_userid = 0,
				tt.last_post_message = '',
				tt.last_post_guest_name = ''
			WHERE tt.moved_id=0 AND tt.hold!=4 AND m.id IS NULL {$topics} {$threads}";
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (JDatabaseExceptionExecuting $e) {
            KunenaError::displayDatabaseError($e);

            return false;
        }

        $rows = $db->getAffectedRows();

        // Find out if there are deleted topics with visible replies.
        $query = $postgresql ? "UPDATE #__kunena_topics AS tt
			SET hold = c.hold
			FROM (
				SELECT m.thread, MIN(m.hold) AS hold FROM #__kunena_messages AS m WHERE m.hold IN (0,1) {$threads} {$range} GROUP BY thread
			) AS c
			WHERE tt.id=c.thread AND tt.moved_id=0 {$topics}" : "UPDATE #__kunena_topics AS tt
			INNER JOIN (
				SELECT m.thread, MIN(m.hold) AS hold FROM #__kunena_messages AS m WHERE m.hold IN (0,1) {$threads} {$range} GROUP BY thread
			) AS c ON tt.id=c.thread
			SET tt.hold = c.hold
			WHERE tt.moved_id=0 {$topics}";
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (JDatabaseExceptionExecuting $e) {
            KunenaError::displayDatabaseError($e);

            return false;
        }

        $rows += $db->getAffectedRows();

        // Recount total posts and attachments, then the first and last post (by time) of each topic.
        // 5.3.0: three statements. The single one used before joined every post of a thread to every other one (first x
        // last) before narrowing them down; MySQL ran it for more than 20 minutes on one batch of 5000 threads with 10 posts
        // each. Now each statement reads the batch's posts once and joins the first/last posts by their ids; of posts
        // sharing the first (last) time, the lowest (highest) id is taken.
        $queries = array();

        $counts = "SELECT m.thread, m.hold, COUNT(DISTINCT m.id) AS posts, COUNT(a.id) AS attachments
				FROM #__kunena_messages AS m
				LEFT JOIN #__kunena_attachments AS a ON m.id=a.mesid
				WHERE m.moved=0 {$threads} {$range}
				GROUP BY m.thread, m.hold";

        $queries[] = $postgresql ? "UPDATE #__kunena_topics AS tt
			SET posts=c.posts, attachments=c.attachments
			FROM ({$counts}) AS c
			WHERE tt.id=c.thread AND c.hold=tt.hold AND tt.moved_id=0 {$topics}" : "UPDATE #__kunena_topics AS tt
			INNER JOIN ({$counts}) AS c ON tt.id=c.thread AND c.hold=tt.hold
			SET tt.posts=c.posts, tt.attachments=c.attachments
			WHERE tt.moved_id=0 {$topics}";

        foreach (array('first' => 'MIN', 'last' => 'MAX') as $which => $agg) {
            // The first (last) post of each thread and hold in one aggregation: time * 2^32 + id orders the posts by time,
            // then by id, and its remainder by 2^32 is the post's id. No join, so no database can pair posts up.
            $post = "SELECT m.thread, m.hold, {$agg}(m.time * 4294967296 + m.id) % 4294967296 AS id
				FROM #__kunena_messages AS m
				WHERE m.moved=0 {$threads} {$range}
				GROUP BY m.thread, m.hold";

            $queries[] = $postgresql ? "UPDATE #__kunena_topics AS tt
				SET {$which}_post_id = mm.id,
					{$which}_post_time = mm.time,
					{$which}_post_userid = mm.userid,
					{$which}_post_message = mt.message,
					{$which}_post_guest_name = mm.name
				FROM ({$post}) AS p, #__kunena_messages AS mm, #__kunena_messages_text AS mt
				WHERE tt.id=p.thread AND p.hold=tt.hold AND mm.id=p.id AND mt.mesid=mm.id AND tt.moved_id=0 {$topics}" : "UPDATE #__kunena_topics AS tt
				INNER JOIN ({$post}) AS p ON tt.id=p.thread AND p.hold=tt.hold
				INNER JOIN #__kunena_messages AS mm ON mm.id=p.id
				INNER JOIN #__kunena_messages_text AS mt ON mt.mesid=mm.id
				SET tt.{$which}_post_id = mm.id,
					tt.{$which}_post_time = mm.time,
					tt.{$which}_post_userid = mm.userid,
					tt.{$which}_post_message = mt.message,
					tt.{$which}_post_guest_name = mm.name
				WHERE tt.moved_id=0 {$topics}";
        }

        foreach ($queries as $query) {
            $db->setQuery($query);

            try {
                $db->execute();
            } catch (JDatabaseExceptionExecuting $e) {
                KunenaError::displayDatabaseError($e);

                return false;
            }

            $rows += $db->getAffectedRows();
        }

        return $rows;
    }

    /**
     * @param   array $topics Topics
     * @param   mixed $user   User
     *
     * @return array|boolean
     * @throws Exception
     * @since Kunena
     */
    public static function fetchNewStatus(array $topics, $user = null)
    {
        $user = KunenaUserHelper::get($user);

        if (!KunenaFactory::getConfig()->shownew || empty($topics) || !$user->exists()) {
            return array();
        }

        $session = KunenaFactory::getSession();

        $ids = array();

        foreach ($topics as $topic) {
            if ($topic->last_post_time < $session->getAllReadTime()) {
                continue;
            }

            $allreadtime = $topic->getCategory()->getUserInfo()->allreadtime;

            if ($allreadtime && $topic->last_post_time < $allreadtime) {
                continue;
            }

            $ids[] = $topic->id;
        }

        if ($ids) {
            $idstr = implode(",", ArrayHelper::toInteger($ids));

            $db = Factory::getDBO();
            $db->setQuery(
                "SELECT m.thread AS id, MIN(m.id) AS lastread, SUM(1) AS unread
				FROM #__kunena_messages AS m
				LEFT JOIN #__kunena_user_read AS ur ON ur.topic_id=m.thread AND user_id={$db->Quote($user->userid)}
				WHERE m.hold=0 AND m.moved=0 AND m.thread IN ({$idstr}) AND m.time>{$db->Quote($session->getAllReadTime())} AND (ur.time IS NULL OR m.time>ur.time)
				GROUP BY thread"
            );

            try {
                $topiclist = (array) $db->loadObjectList('id');
            } catch (JDatabaseExceptionExecuting $e) {
                KunenaError::displayDatabaseError($e);

                return false;
            }
        }

        $list = array();

        foreach ($topics as $topic) {
            if (isset($topiclist[$topic->id])) {
                $topic->lastread = $topiclist[$topic->id]->lastread;
                $topic->unread   = $topiclist[$topic->id]->unread;
            } else {
                $topic->lastread = $topic->last_post_id;
                $topic->unread   = 0;
            }

            $list[$topic->id] = $topic->lastread;
        }

        return $list;
    }
}

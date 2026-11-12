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
use Joomla\String\StringHelper;

/**
 * Finds forum threads related to a question, with the search queries written by the triage step.
 *
 * Only published posts in categories guests can read are searched (nothing private reaches the AI provider), the
 * thread being answered and Baldrick's own posts are left out (so it never learns from itself).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaBaldrickRetriever
{
    /**
     * Characters of a post excerpt
     *
     * @since 5.3.0
     */
    public const EXCERPT = 900;

    /**
     * Search words at most (from all the queries)
     *
     * @since 5.3.0
     */
    public const MAX_TERMS = 8;

    /**
     * Related threads.
     *
     * @param   array    $queries  Search queries
     * @param   integer  $exclude  Thread to leave out
     * @param   integer  $limit    Number of threads
     *
     * @return  array  [['id', 'subject', 'link', 'score', 'posts' => [['author', 'staff', 'text'], ...]], ...]
     *
     * @since 5.3.0
     */
    public static function search(array $queries, $exclude, $limit = 5)
    {
        $limit      = max(0, (int) $limit);
        $categories = KunenaBaldrick::getPublicCategories();

        if (!$limit || !$categories || !$queries) {
            return array();
        }

        // Every word of every query, in one scan of the posts (a scan per query was slow on large forums)
        $words = array();

        foreach ($queries as $query) {
            $words = array_merge($words, static::terms($query));
        }

        $words = array_slice(array_values(array_unique($words)), 0, static::MAX_TERMS);

        if (!$words) {
            return array();
        }

        $scores = array();
        $start  = microtime(true);

        // With the full-text index every post is searched quickly; without it, the latest posts with LIKE
        if (static::hasIndex()) {
            static::$lastMode = 'full-text';
            $threads          = static::findIndexed($words, $categories, $exclude);
        } else {
            static::$lastMode = 'plain';
            $threads          = static::find($words, $categories, $exclude, static::getMinPostId());
        }

        static::$lastSeconds = round(microtime(true) - $start, 2);
        $rank                = count($threads);

        foreach ($threads as $id => $row) {
            // In the database's order: the best post first (most words, or relevance), then how many posts match
            $scores[$id] = $rank-- + min($row['hits'], 10) / 100;
        }

        if (!$scores) {
            return array();
        }

        arsort($scores);

        $result = array();

        if (static::$lastMode !== 'full-text') {
            foreach (array_slice(array_keys($scores), 0, $limit) as $id) {
                $item = static::excerpt($id, $words);

                if ($item) {
                    $item['score'] = round($scores[$id], 2);
                    $result[]      = $item;
                }
            }

            return $result;
        }

        // Full-text candidates match any of the words: keep those matching as many as the plain search requires, best
        // first by the distinct words of their best post (then the database's order)
        $required   = min(2, count($words));
        $candidates = array();
        $position   = 0;

        foreach (array_keys($scores) as $id) {
            $item = static::excerpt($id, $words);

            if (!$item) {
                continue;
            }

            $found = static::countWords($item, $words);

            if ($found >= $required) {
                $item['score'] = $found;
                $candidates[]  = array($found, $position++, $item);
            }
        }

        usort($candidates, function ($a, $b) {
            return $a[0] === $b[0] ? $a[1] - $b[1] : $b[0] - $a[0];
        });

        foreach (array_slice($candidates, 0, $limit) as $candidate) {
            $result[] = $candidate[2];
        }

        return $result;
    }

    /**
     * @param   array     $item   Excerpt of a thread
     * @param   string[]  $words  Search words
     *
     * @return  integer  Distinct words found in the best matching post of the excerpt
     *
     * @since 5.3.0
     */
    protected static function countWords(array $item, array $words)
    {
        $best = 0;

        foreach ($item['posts'] as $post) {
            $text  = StringHelper::strtolower($item['subject'] . ' ' . $post['text']);
            $count = 0;

            foreach ($words as $word) {
                $count += strpos($text, $word) !== false ? 1 : 0;
            }

            $best = max($best, $count);
        }

        return $best;
    }

    /**
     * @param   string  $query  Query
     *
     * @return  string[]  Search words (3 characters or more, up to 6)
     *
     * @since 5.3.0
     */
    public static function terms($query)
    {
        $words = preg_split('/[^\p{L}\p{N}_.\-]+/u', StringHelper::strtolower((string) $query), -1, PREG_SPLIT_NO_EMPTY);
        $terms = array();

        foreach ((array) $words as $word) {
            $word = trim($word, '.-');

            if (StringHelper::strlen($word) >= 3) {
                $terms[$word] = $word;
            }
        }

        return array_slice(array_values($terms), 0, 6);
    }

    /**
     * @var string  How the last search ran: full-text or plain
     * @since 5.3.0
     */
    public static $lastMode = '';

    /**
     * @var float  Seconds the last search took
     * @since 5.3.0
     */
    public static $lastSeconds = 0;

    /**
     * @var boolean|null  The full-text index exists (cached for the request)
     * @since 5.3.0
     */
    protected static $index = null;

    /**
     * @return  string  Name of the full-text index (prefixed table name style, for PostgreSQL where index names are global)
     *
     * @since 5.3.0
     */
    protected static function indexName()
    {
        return Factory::getDbo()->replacePrefix('#__kunena_baldrick_fts');
    }

    /**
     * @return  string  unsupported (SQLite, other databases), present or missing
     *
     * @since 5.3.0
     */
    public static function indexStatus()
    {
        $db = Factory::getDbo();

        if (!KunenaDatabaseHelper::isMysql($db) && !KunenaDatabaseHelper::isPostgresql($db)) {
            return 'unsupported';
        }

        return static::hasIndex(true) ? 'present' : 'missing';
    }

    /**
     * @param   boolean  $reload  Check again
     *
     * @return  boolean  The full-text index of the posts exists
     *
     * @since 5.3.0
     */
    public static function hasIndex($reload = false)
    {
        if (static::$index !== null && !$reload) {
            return static::$index;
        }

        $db    = Factory::getDbo();
        $table = $db->replacePrefix('#__kunena_messages_text');

        try {
            if (KunenaDatabaseHelper::isMysql($db)) {
                $query = 'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE()'
                    . ' AND table_name = ' . $db->quote($table) . ' AND index_name = ' . $db->quote(static::indexName());
            } elseif (KunenaDatabaseHelper::isPostgresql($db)) {
                $query = 'SELECT COUNT(*) FROM pg_indexes WHERE tablename = ' . $db->quote($table) . ' AND indexname = ' . $db->quote(static::indexName());
            } else {
                return static::$index = false;
            }

            static::$index = (int) $db->setQuery($query)->loadResult() > 0;
        } catch (Exception $e) {
            static::$index = false;
        }

        return static::$index;
    }

    /**
     * Creates the full-text index of the posts: MySQL/MariaDB FULLTEXT (InnoDB needs MySQL 5.6+ or MariaDB 10.0.5+),
     * PostgreSQL GIN on to_tsvector('simple', message), built without locking the table. Takes a few seconds per
     * 100,000 posts; on MySQL posting waits meanwhile.
     *
     * @return  void
     *
     * @throws  Exception  The database refused (e.g. InnoDB on MySQL 5.5)
     * @since 5.3.0
     */
    public static function createIndex()
    {
        $db = Factory::getDbo();

        if (static::hasIndex(true)) {
            return;
        }

        if (KunenaDatabaseHelper::isMysql($db)) {
            $db->setQuery('ALTER TABLE ' . $db->quoteName('#__kunena_messages_text') . ' ADD FULLTEXT INDEX ' . $db->quoteName(static::indexName()) . ' (' . $db->quoteName('message') . ')')->execute();
        } elseif (KunenaDatabaseHelper::isPostgresql($db)) {
            $db->setQuery('CREATE INDEX CONCURRENTLY IF NOT EXISTS ' . $db->quoteName(static::indexName()) . ' ON ' . $db->quoteName('#__kunena_messages_text')
                . ' USING GIN (to_tsvector(\'simple\', ' . $db->quoteName('message') . '))')->execute();
        } else {
            throw new RuntimeException('This database has no full-text index: the plain search is used.');
        }

        static::hasIndex(true);
    }

    /**
     * Threads with posts matching the words, with the full-text index.
     *
     * @param   string[]   $terms       Words
     * @param   integer[]  $categories  Categories
     * @param   integer    $exclude     Thread to leave out
     *
     * @return  array  thread => ['best' => relevance, 'hits' => posts], best first (20 at most)
     *
     * @since 5.3.0
     */
    protected static function findIndexed(array $terms, array $categories, $exclude)
    {
        $db = Factory::getDbo();

        if (KunenaDatabaseHelper::isPostgresql($db)) {
            $words = array();

            foreach ($terms as $term) {
                $word = preg_replace('/[^\p{L}\p{N}_]+/u', '', $term);

                if ($word !== '') {
                    $words[] = $word;
                }
            }

            if (!$words) {
                return array();
            }

            $vector = 'to_tsvector(\'simple\', ' . $db->quoteName('t.message') . ')';
            $tsq    = 'to_tsquery(\'simple\', ' . $db->quote(implode(' | ', $words)) . ')';
            $match  = $vector . ' @@ ' . $tsq;
            // No ts_rank(): it rebuilds the vector of every matching post (20 times slower); the candidates are ranked
            // by their words afterwards (search())
            $score  = '1';
        } else {
            $against = 'AGAINST(' . $db->quote(implode(' ', $terms)) . ')';
            $match   = 'MATCH(' . $db->quoteName('t.message') . ') ' . $against;
            $score   = $match;
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('m.thread') . ', MAX(' . $score . ') AS ' . $db->quoteName('best') . ', COUNT(*) AS ' . $db->quoteName('hits'))
            ->from($db->quoteName('#__kunena_messages_text', 't'))
            ->innerJoin($db->quoteName('#__kunena_messages', 'm') . ' ON ' . $db->quoteName('m.id') . ' = ' . $db->quoteName('t.mesid'))
            ->innerJoin($db->quoteName('#__kunena_topics', 'tt') . ' ON ' . $db->quoteName('tt.id') . ' = ' . $db->quoteName('m.thread'))
            ->where($match)
            ->where($db->quoteName('m.hold') . ' = 0')
            ->where($db->quoteName('tt.hold') . ' = 0')
            ->where($db->quoteName('tt.moved_id') . ' = 0')
            ->where($db->quoteName('m.catid') . ' IN (' . implode(',', array_map('intval', $categories)) . ')')
            ->where($db->quoteName('m.thread') . ' != ' . (int) $exclude)
            ->group($db->quoteName('m.thread'))
            ->order($db->quoteName('best') . ' DESC, ' . $db->quoteName('hits') . ' DESC, MAX(' . $db->quoteName('m.time') . ') DESC');

        $userid = KunenaBaldrick::getUserId();

        if ($userid) {
            $query->where($db->quoteName('m.userid') . ' != ' . $userid);
        }

        try {
            $rows = $db->setQuery($query, 0, 20)->loadObjectList();
        } catch (Exception $e) {
            // E.g. the index was dropped: the plain search
            static::$index = false;

            return static::find($terms, $categories, $exclude, static::getMinPostId());
        }

        $threads = array();

        foreach ((array) $rows as $row) {
            $threads[(int) $row->thread] = array('best' => (float) $row->best, 'hits' => (int) $row->hits);
        }

        return $threads;
    }

    /**
     * The oldest post searched: on very large forums only the latest posts are searched (setting "retrieval_max_posts").
     *
     * @return  integer  Post ID (0: all posts)
     *
     * @since 5.3.0
     */
    public static function getMinPostId()
    {
        $max = (int) KunenaBaldrick::getParams()->get('retrieval_max_posts', 50000);

        if ($max <= 0) {
            return 0;
        }

        $db   = Factory::getDbo();
        $last = (int) $db->setQuery($db->getQuery(true)->select('MAX(' . $db->quoteName('id') . ')')->from($db->quoteName('#__kunena_messages')))->loadResult();

        return max(0, $last - $max);
    }

    /**
     * Threads with posts matching the words, in a single scan: for each thread, the most distinct words one post matches
     * (at least two when there are several words) and the number of matching posts.
     *
     * @param   string[]   $terms       Words
     * @param   integer[]  $categories  Categories
     * @param   integer    $exclude     Thread to leave out
     * @param   integer    $minId       Search posts after this ID only (0: all)
     *
     * @return  array  thread => ['best' => words, 'hits' => posts], best first (20 at most)
     *
     * @since 5.3.0
     */
    protected static function find(array $terms, array $categories, $exclude, $minId = 0)
    {
        $db      = Factory::getDbo();
        $like    = KunenaDatabaseHelper::like($db);
        $matches = array();

        foreach ($terms as $term) {
            $pattern   = $db->quote('%' . $db->escape($term, true) . '%');
            $matches[] = '(' . $db->quoteName('t.message') . ' ' . $like . ' ' . $pattern . ' OR ' . $db->quoteName('m.subject') . ' ' . $like . ' ' . $pattern . ')';
        }

        $score = '(' . implode(' + ', array_map(function ($match) {
            return 'CASE WHEN ' . $match . ' THEN 1 ELSE 0 END';
        }, $matches)) . ')';

        $query = $db->getQuery(true)
            ->select($db->quoteName('m.thread') . ', MAX(' . $score . ') AS ' . $db->quoteName('best') . ', COUNT(*) AS ' . $db->quoteName('hits'))
            ->from($db->quoteName('#__kunena_messages', 'm'))
            ->innerJoin($db->quoteName('#__kunena_messages_text', 't') . ' ON ' . $db->quoteName('t.mesid') . ' = ' . $db->quoteName('m.id'))
            ->innerJoin($db->quoteName('#__kunena_topics', 'tt') . ' ON ' . $db->quoteName('tt.id') . ' = ' . $db->quoteName('m.thread'))
            ->where($db->quoteName('m.hold') . ' = 0')
            ->where($db->quoteName('tt.hold') . ' = 0')
            ->where($db->quoteName('tt.moved_id') . ' = 0')
            ->where($db->quoteName('m.catid') . ' IN (' . implode(',', array_map('intval', $categories)) . ')')
            ->where($db->quoteName('m.thread') . ' != ' . (int) $exclude)
            ->where('(' . implode(' OR ', $matches) . ')')
            ->group($db->quoteName('m.thread'))
            ->having('MAX(' . $score . ') >= ' . min(2, count($terms)))
            ->order($db->quoteName('best') . ' DESC, ' . $db->quoteName('hits') . ' DESC, MAX(' . $db->quoteName('m.time') . ') DESC');

        if ($minId > 0) {
            // The primary key: no scan of the older posts
            $query->where($db->quoteName('m.id') . ' > ' . (int) $minId);
        }

        $userid = KunenaBaldrick::getUserId();

        if ($userid) {
            $query->where($db->quoteName('m.userid') . ' != ' . $userid);
        }

        try {
            $rows = $db->setQuery($query, 0, 20)->loadObjectList();
        } catch (Exception $e) {
            return array();
        }

        $threads = array();

        foreach ((array) $rows as $row) {
            $threads[(int) $row->thread] = array('best' => (int) $row->best, 'hits' => (int) $row->hits);
        }

        return $threads;
    }

    /**
     * Excerpt of a thread: its first post and the two replies matching the most words (staff replies first).
     *
     * @param   integer   $id     Thread
     * @param   string[]  $words  Search words
     *
     * @return  array|null
     *
     * @since 5.3.0
     */
    protected static function excerpt($id, array $words)
    {
        $topic = KunenaForumTopicHelper::get($id);

        if (!$topic->exists()) {
            return null;
        }

        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName(array('m.id', 'm.userid', 'm.name', 'm.time', 't.message')))
            ->from($db->quoteName('#__kunena_messages', 'm'))
            ->innerJoin($db->quoteName('#__kunena_messages_text', 't') . ' ON ' . $db->quoteName('t.mesid') . ' = ' . $db->quoteName('m.id'))
            ->where($db->quoteName('m.thread') . ' = ' . (int) $id)
            ->where($db->quoteName('m.hold') . ' = 0')
            ->order($db->quoteName('m.time') . ' ASC, ' . $db->quoteName('m.id') . ' ASC');

        $userid = KunenaBaldrick::getUserId();

        if ($userid) {
            $query->where($db->quoteName('m.userid') . ' != ' . $userid);
        }

        $rows = (array) $db->setQuery($query, 0, 60)->loadObjectList();

        if (!$rows) {
            return null;
        }

        $category = $topic->getCategory();
        $first    = array_shift($rows);
        $ranked   = array();

        foreach ($rows as $index => $row) {
            $text  = StringHelper::strtolower((string) $row->message);
            $score = 0;

            foreach ($words as $word) {
                $score += strpos($text, $word) !== false ? 1 : 0;
            }

            $staff = KunenaBaldrick::isStaff($row->userid, $category);

            if ($score || $staff) {
                $ranked[] = array($score + ($staff ? 2 : 0), $index, $row, $staff);
            }
        }

        usort($ranked, function ($a, $b) {
            return $a[0] == $b[0] ? $a[1] - $b[1] : ($a[0] < $b[0] ? 1 : -1);
        });

        $posts = array(static::post($first, KunenaBaldrick::isStaff($first->userid, $category)));

        foreach (array_slice($ranked, 0, 2) as $item) {
            $posts[] = static::post($item[2], $item[3]);
        }

        return array(
            'id'      => (int) $topic->id,
            'subject' => (string) $topic->subject,
            'link'    => KunenaBaldrick::topicLink($topic->category_id, $topic->id),
            'posts'   => $posts,
        );
    }

    /**
     * @param   object   $row    Post
     * @param   boolean  $staff  Written by staff
     *
     * @return  array
     *
     * @since 5.3.0
     */
    protected static function post($row, $staff)
    {
        $text = KunenaBaldrick::plainText($row->message);

        if (StringHelper::strlen($text) > static::EXCERPT) {
            $text = StringHelper::substr($text, 0, static::EXCERPT) . ' [...]';
        }

        return array(
            'author' => (string) ($row->userid ? KunenaUserHelper::get($row->userid)->getName($row->name) : $row->name),
            'staff'  => (bool) $staff,
            'text'   => $text,
        );
    }
}

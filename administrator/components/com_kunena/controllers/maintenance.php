<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator
 * @subpackage    Controllers
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\Utilities\ArrayHelper;

/**
 * Kunena > Maintenance: every maintenance job behind one JSON task.
 *
 * Request (POST): task=run&format=json, job=<name>, step=init|run, the job's own fields and a form token. Answer:
 * success, done, status (progress, e.g. "40%"), message, errors[], and for some jobs rows[]. Jobs that take long run
 * in steps of a few seconds (their state is kept in the session): the page calls again while "done" is false.
 *
 * @since 5.5.0 (JoomlaWorks Ltd.)
 */
#[\AllowDynamicProperties]
class KunenaAdminControllerMaintenance extends KunenaController
{
    /**
     * Seconds of work per step for the jobs that run in steps
     *
     * @since 5.5.0
     */
    public const STEP_SECONDS = 5;

    /**
     * Job name => method
     *
     * @since 5.5.0
     */
    protected static $jobs = array(
        'recount_topics'     => 'jobRecountTopics',
        'recount_usertopics' => 'jobRecountUserTopics',
        'recount_categories' => 'jobRecountCategories',
        'recount_users'      => 'jobRecountUsers',
        'recount_polls'      => 'jobRecountPolls',
        'users_add'          => 'jobUsersAdd',
        'users_remove'       => 'jobUsersRemove',
        'users_rename'       => 'jobUsersRename',
        'users_lifetime'     => 'jobUsersLifetime',
        'names_preview'      => 'jobNamesPreview',
        'names_normalize'    => 'jobNamesNormalize',
        'ip_posts'           => 'jobIpPosts',
        'ip_users'           => 'jobIpUsers',
        'purge_re'           => 'jobPurgeRe',
        'prune'              => 'jobPrune',
        'empty_trash'        => 'jobEmptyTrash',
    );

    /**
     * @var    string
     * @since  5.5.0
     */
    protected $baseurl = 'administrator/index.php?option=com_kunena&view=maintenance';

    /**
     * Runs one step of a job.
     *
     * @return  void
     *
     * @since 5.5.0
     */
    public function run()
    {
        KunenaFactory::loadLanguage('com_kunena.views', 'admin');
        KunenaFactory::loadLanguage('com_kunena.controllers', 'admin');
        KunenaFactory::loadLanguage('com_kunena.models', 'admin');

        if (!Session::checkToken('post')) {
            $this->respond(array('success' => false, 'message' => Text::_('COM_KUNENA_ERROR_TOKEN')));
        }

        if (!Factory::getUser()->authorise('core.admin', 'com_kunena')) {
            $this->respond(array('success' => false, 'message' => Text::_('COM_KUNENA_MAINTENANCE_NOT_ALLOWED')));
        }

        $job = $this->input->getCmd('job');

        if (!isset(static::$jobs[$job])) {
            $this->respond(array('success' => false, 'message' => Text::_('COM_KUNENA_MAINTENANCE_UNKNOWN_JOB')));
        }

        $key   = 'com_kunena.admin.maintenance.' . $job;
        $state = $this->app->getUserState($key, null);

        if ($this->input->getCmd('step', 'init') === 'init' || !is_array($state)) {
            $state = array();
        }

        @set_time_limit(120);

        try {
            $method = static::$jobs[$job];
            $result = $this->$method($state);
        } catch (Throwable $e) {
            $this->app->setUserState($key, null);
            $this->respond(array('success' => false, 'message' => $e->getMessage()));
        }

        $result += array('success' => true, 'done' => true, 'status' => '', 'message' => '', 'errors' => array());
        $this->app->setUserState($key, $result['done'] ? null : $state);

        if ($result['done'] && $result['status'] === '') {
            $result['status'] = '100%';
        }

        $this->respond($result);
    }

    /**
     * Thread statistics (posts, first and last post), in ranges of thread ids; orphan attachments are cleaned first.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobRecountTopics(array &$state)
    {
        return $this->recountRanges($state, 'KunenaForumTopicHelper', 'COM_KUNENA_ADMIN_RECOUNT_TOPICS_X', true);
    }

    /**
     * Users' thread statistics, in ranges of thread ids.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobRecountUserTopics(array &$state)
    {
        return $this->recountRanges($state, 'KunenaForumTopicUserHelper', 'COM_KUNENA_ADMIN_RECOUNT_USERTOPICS_X', false);
    }

    /**
     * @param   array    $state        Job state (start, max)
     * @param   string   $helper       Class with recount($topicids, $start, $end)
     * @param   string   $progress     Progress string (%s: percentage)
     * @param   boolean  $attachments  Clean orphan attachments before the first range
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function recountRanges(array &$state, $helper, $progress, $attachments)
    {
        if (!isset($state['max'])) {
            $db             = Factory::getDbo();
            $state['max']   = (int) $db->setQuery('SELECT MAX(' . $db->quoteName('thread') . ') FROM ' . $db->quoteName('#__kunena_messages'))->loadResult();
            $state['start'] = 0;

            if ($attachments) {
                KunenaAttachmentHelper::cleanup();
            }
        }

        $until = microtime(true) + static::STEP_SECONDS;

        while ($state['start'] <= $state['max'] && microtime(true) < $until) {
            $helper::recount(false, $state['start'], $state['start'] + 5000);
            $state['start'] += 5000;
        }

        $done    = $state['start'] > $state['max'];
        $percent = $done ? 100 : (int) floor(100 * $state['start'] / max(1, $state['max']));

        return array(
            'done'    => $done,
            'status'  => $percent . '%',
            'message' => $done ? Text::_('COM_KUNENA_MAINTENANCE_DONE') : Text::sprintf($progress, $percent . '%'),
        );
    }

    /**
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobRecountCategories(array &$state)
    {
        KunenaForumCategoryHelper::recount();
        KunenaForumCategoryHelper::fixAliases();

        return array('message' => Text::_('COM_KUNENA_RECOUNTFORUMS_DONE'));
    }

    /**
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobRecountUsers(array &$state)
    {
        KunenaForumMessageThankyouHelper::recountThankyou();
        KunenaUserHelper::recount();
        KunenaUserHelper::recountPostsNull();

        return array('message' => Text::_('COM_KUNENA_MAINTENANCE_DONE'));
    }

    /**
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobRecountPolls(array &$state)
    {
        KunenaForumTopicPollHelper::recount();

        return array('message' => Text::_('COM_KUNENA_MAINTENANCE_DONE'));
    }

    /**
     * A Kunena profile for every Joomla account which has none.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobUsersAdd(array &$state)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            "INSERT INTO #__kunena_users (userid, `showOnline`)
                SELECT a.id AS userid, 1 AS `showOnline`
                FROM #__users AS a
                LEFT JOIN #__kunena_users AS b ON b.userid=a.id
                WHERE b.userid IS NULL"
        )->execute();

        return array('message' => Text::sprintf('COM_KUNENA_SYNC_USERS_ADD_DONE', $db->getAffectedRows()));
    }

    /**
     * Removes the Kunena profiles of deleted Joomla accounts.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobUsersRemove(array &$state)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            KunenaDatabaseHelper::choose(
                array(
                    'mysql'      => "DELETE a FROM #__kunena_users AS a LEFT JOIN #__users AS b ON a.userid=b.id WHERE b.id IS NULL",
                    'postgresql' => "DELETE FROM #__kunena_users AS a WHERE NOT EXISTS (SELECT 1 FROM #__users AS b WHERE b.id = a.userid)",
                    'sqlite'     => "DELETE FROM #__kunena_users WHERE userid NOT IN (SELECT id FROM #__users)",
                )
            )
        )->execute();

        return array('message' => Text::sprintf('COM_KUNENA_SYNC_USERS_DELETE_DONE', $db->getAffectedRows()));
    }

    /**
     * The author name stored with every post, from the account (username or name, as Configuration > Members says).
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobUsersRename(array &$state)
    {
        $db     = Factory::getDbo();
        $column = KunenaConfig::getInstance()->username ? 'username' : 'name';
        $db->setQuery(
            KunenaDatabaseHelper::choose(
                array(
                    'mysql'      => "UPDATE #__kunena_messages AS m INNER JOIN #__users AS u SET m.name = u.{$column} WHERE m.userid = u.id",
                    'postgresql' => "UPDATE #__kunena_messages AS m SET name = u.{$column} FROM #__users AS u WHERE m.userid = u.id",
                )
            )
        )->execute();

        return array('message' => Text::sprintf('COM_KUNENA_SYNC_USERS_RENAME_DONE', $db->getAffectedRows()));
    }

    /**
     * Deletes the Joomla accounts of members banned from the forum for life (and their Kunena profiles), through
     * Joomla (so that groups, sessions and other extensions' data go too); Super Users and the current user are never
     * deleted. Their posts stay.
     *
     * @param   array  $state  Job state (total, deleted, skip)
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobUsersLifetime(array &$state)
    {
        $db    = Factory::getDbo();
        $query = function () use ($db, &$state) {
            $query = $db->getQuery(true)
                ->from($db->quoteName('#__kunena_users', 'k'))
                ->innerJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('k.userid'))
                ->where($db->quoteName('k.banned') . ' = ' . $db->quote('9999-12-31 23:59:59'))
                ->where($db->quoteName('u.id') . ' <> ' . (int) Factory::getUser()->id);

            if (!empty($state['skip'])) {
                $query->where($db->quoteName('u.id') . ' NOT IN (' . implode(',', array_map('intval', $state['skip'])) . ')');
            }

            return $query;
        };

        if (!isset($state['total'])) {
            $state += array('total' => (int) $db->setQuery($query()->select('COUNT(*)'))->loadResult(), 'deleted' => 0, 'skip' => array());
        }

        $errors = array();
        $until  = microtime(true) + static::STEP_SECONDS;

        while (microtime(true) < $until) {
            $ids = $db->setQuery($query()->select($db->quoteName('u.id'))->order($db->quoteName('u.id')), 0, 25)->loadColumn();

            if (!$ids) {
                break;
            }

            foreach ($ids as $id) {
                $user = Factory::getUser((int) $id);

                if ($user->authorise('core.admin') || !$user->delete()) {
                    $state['skip'][] = (int) $id;
                    $errors[]        = Text::sprintf('COM_KUNENA_MAINTENANCE_LIFETIME_KEPT', $user->username, (int) $id);
                    continue;
                }

                $db->setQuery('DELETE FROM ' . $db->quoteName('#__kunena_users') . ' WHERE ' . $db->quoteName('userid') . ' = ' . (int) $id)->execute();
                $state['deleted']++;
            }
        }

        $left = (int) $db->setQuery($query()->select('COUNT(*)'))->loadResult();

        return array(
            'done'    => !$left,
            'status'  => (int) floor(100 * ($state['total'] - $left) / max(1, $state['total'])) . '%',
            'message' => Text::sprintf('COM_KUNENA_MAINTENANCE_LIFETIME_DONE', $state['deleted']),
            'errors'  => $errors,
        );
    }

    /**
     * The first 50 changes Normalise Usernames & Names would make (nothing is written).
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobNamesPreview(array &$state)
    {
        if (!KunenaUserNormalizer::isEnabled()) {
            return array('message' => Text::_('COM_KUNENA_NORMALIZENAMES_DISABLED_SHORT'));
        }

        $counts = KunenaUserNormalizer::count();
        $rows   = array();

        foreach (KunenaUserNormalizer::normalize(50, 0, true)['changed'] as $id => $change) {
            $rows[] = array(
                'id'       => (int) $id,
                'username' => $change[2] !== null ? $change[0] . ' → ' . $change[2] : $change[0],
                'name'     => $change[3] !== null ? $change[1] . ' → ' . $change[3] : $change[1],
            );
        }

        return array(
            'message' => $counts['accounts'] ? Text::sprintf('COM_KUNENA_NORMALIZENAMES_COUNTS', $counts['accounts'], $counts['usernames'], $counts['names'])
                : Text::_('COM_KUNENA_NORMALIZENAMES_NONE'),
            'rows'    => $rows,
            'columns' => array('id' => Text::_('JGRID_HEADING_ID'), 'username' => Text::_('COM_KUNENA_NORMALIZENAMES_COL_USERNAME'), 'name' => Text::_('COM_KUNENA_NORMALIZENAMES_COL_NAME')),
        );
    }

    /**
     * Rewrites the usernames and names that break the rules (KunenaUserNormalizer), in steps.
     *
     * @param   array  $state  Job state (total, after, accounts, usernames, names)
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobNamesNormalize(array &$state)
    {
        if (!KunenaUserNormalizer::isEnabled()) {
            return array('message' => Text::_('COM_KUNENA_NORMALIZENAMES_DISABLED_SHORT'));
        }

        if (!isset($state['total'])) {
            $state = array('total' => KunenaUserNormalizer::count()['accounts'], 'after' => 0, 'accounts' => 0, 'usernames' => 0, 'names' => 0);
        }

        $until = microtime(true) + static::STEP_SECONDS;

        do {
            $result = KunenaUserNormalizer::normalize(100, $state['after']);

            foreach ($result['changed'] as $change) {
                $state['accounts']++;
                $state['usernames'] += $change[2] !== null ? 1 : 0;
                $state['names'] += $change[3] !== null ? 1 : 0;
            }

            $state['after'] = $result['last'];
        } while ($state['after'] && microtime(true) < $until);

        if ($state['after']) {
            return array(
                'done'    => false,
                'status'  => min(99, (int) floor(100 * $state['accounts'] / max(1, $state['total']))) . '%',
                'message' => Text::sprintf('COM_KUNENA_NORMALIZENAMES_PROGRESS', $state['accounts'], $state['total']),
            );
        }

        return array('message' => Text::sprintf('COM_KUNENA_NORMALIZENAMES_DONE', $state['accounts'], $state['usernames'], $state['names']));
    }

    /**
     * Removes the IP addresses stored with posts older than the given days (0: all posts).
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobIpPosts(array &$state)
    {
        $db    = Factory::getDbo();
        $days  = max(0, $this->input->getInt('days', 365));
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__kunena_messages'))
            ->set($db->quoteName('ip') . ' = NULL')
            ->where($db->quoteName('ip') . ' IS NOT NULL');

        if ($days) {
            $query->where($db->quoteName('time') . ' < ' . (int) (Factory::getDate()->toUnix() - $days * 86400));
        }

        $db->setQuery($query)->execute();
        $count = $db->getAffectedRows();

        return array('message' => $count ? Text::sprintf('COM_KUNENA_TOOLS_CLEANUP_IP_DONE', $count) : Text::_('COM_KUNENA_TOOLS_CLEANUP_IP_FAILED'));
    }

    /**
     * Removes the IP addresses stored with every member's profile.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobIpUsers(array &$state)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_users'))
                ->set($db->quoteName('ip') . ' = ' . $db->quote(''))
                ->where($db->quoteName('ip') . ' <> ' . $db->quote(''))
        )->execute();

        return array('message' => Text::sprintf('COM_KUNENA_MAINTENANCE_IP_USERS_DONE', $db->getAffectedRows()));
    }

    /**
     * Removes a prefix ("Re:") from the subjects of the posts that start with it, repeated prefixes too ("Re: Re:"),
     * without case like the LIKE that finds them. In PHP and in steps: the same on every database, and replies are
     * usually most of a forum's posts.
     *
     * @param   array  $state  Job state (prefix, after, count)
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobPurgeRe(array &$state)
    {
        if (!isset($state['prefix'])) {
            $prefix = trim($this->input->getString('prefix', ''));

            if ($prefix === '') {
                return array('success' => false, 'message' => Text::_('COM_KUNENA_MENU_RE_PURGE_FORGOT_STATEMENT'));
            }

            $state = array('prefix' => $prefix, 'after' => 0, 'count' => 0);
        }

        $db     = Factory::getDbo();
        $prefix = $state['prefix'];
        $strip  = '/^(?:\s*' . preg_quote($prefix, '/') . ')+\s*/iu';
        $until  = microtime(true) + static::STEP_SECONDS;
        $rows   = true;

        while ($rows && microtime(true) < $until) {
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(array('id', 'subject')))
                    ->from($db->quoteName('#__kunena_messages'))
                    ->where($db->quoteName('id') . ' > ' . (int) $state['after'])
                    ->where($db->quoteName('subject') . ' ' . KunenaDatabaseHelper::like() . ' ' . $db->quote($db->escape($prefix, true) . '%', false))
                    ->order($db->quoteName('id')),
                0,
                500
            )->loadObjectList();

            foreach ($rows as $row) {
                $state['after'] = (int) $row->id;
                $subject        = (string) preg_replace($strip, '', (string) $row->subject);

                if ($subject !== '' && $subject !== $row->subject) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__kunena_messages'))
                            ->set($db->quoteName('subject') . ' = ' . $db->quote($subject))
                            ->where($db->quoteName('id') . ' = ' . (int) $row->id)
                    )->execute();
                    $state['count']++;
                }
            }
        }

        if ($rows) {
            return array('done' => false, 'status' => '', 'message' => Text::sprintf('COM_KUNENA_MENU_RE_PURGED', $state['count'], $prefix));
        }

        return array(
            'message' => $state['count'] ? Text::sprintf('COM_KUNENA_MENU_RE_PURGED', $state['count'], $prefix) : Text::sprintf('COM_KUNENA_MENU_RE_PURGE_FAILED', $prefix),
        );
    }

    /**
     * Trashes or deletes the threads of the chosen categories without new posts for the given days.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobPrune(array &$state)
    {
        $ids        = ArrayHelper::toInteger((array) $this->input->get('categories', array(), 'array'));
        $categories = $ids ? KunenaForumCategoryHelper::getCategories($ids, false, 'admin') : array();

        if (!$categories) {
            return array('success' => false, 'message' => Text::_('COM_KUNENA_CHOOSEFORUMTOPRUNE'));
        }

        $days   = max(0, $this->input->getInt('days', 30));
        $date   = Factory::getDate()->toUnix() - $days * 86400;
        $delete = $this->input->getInt('delete', 0) === 1;
        $only   = array(
            'answered'   => 'AND tt.posts>1',
            'unanswered' => 'AND tt.posts=1',
            'locked'     => 'AND tt.locked>0',
            'deleted'    => 'AND tt.hold IN (2,3)',
            'unapproved' => 'AND tt.hold=1',
            'shadow'     => 'AND tt.moved_id>0',
            'normal'     => 'AND tt.locked=0',
            'all'        => '',
        );
        $filter = $this->input->getCmd('only', 'normal');
        $where  = array(' AND tt.last_post_time < ' . (int) $date, isset($only[$filter]) ? $only[$filter] : 'AND 0');

        if ($this->input->getInt('keepsticky', 1)) {
            $where[] = ' AND tt.ordering=0';
        }

        $params = array('where' => implode(' ', $where));
        $count  = 0;

        foreach ($categories as $category) {
            $count += $delete ? $category->purge($date, $params) : $category->trash($date, $params);
        }

        return array('message' => Text::sprintf($delete ? 'COM_KUNENA_MAINTENANCE_PRUNE_DELETED' : 'COM_KUNENA_MAINTENANCE_PRUNE_TRASHED', $count, $days));
    }

    /**
     * Permanently deletes every trashed thread and post (KunenaForumTrash), in steps.
     *
     * @param   array  $state  Job state
     *
     * @return  array
     *
     * @since 5.5.0
     */
    protected function jobEmptyTrash(array &$state)
    {
        if (!isset($state['total'])) {
            $counts = KunenaForumTrash::count();
            $state  = array(
                'total'    => $counts['topics'] + $counts['messages'],
                'topics'   => 0,
                'messages' => 0,
                'affected' => array(),
                'skip'     => array('topics' => array(), 'messages' => array()),
                'errors'   => array(),
            );
        }

        $skip   = $state['skip'];
        $result = KunenaForumTrash::purge(10, $skip);

        $state['skip']      = $skip;
        $state['topics']   += $result['topics'];
        $state['messages'] += $result['messages'];
        $state['affected']  = array_slice($state['affected'] + $result['affected'], 0, 10000, true);
        $state['errors']    = array_slice(array_merge($state['errors'], $result['errors']), 0, 50);

        if (!$result['done']) {
            $done = $state['topics'] + $state['messages'] + count($state['skip']['topics']) + count($state['skip']['messages']);

            return array(
                'done'    => false,
                'status'  => min(99, (int) floor(100 * $done / max(1, $state['total']))) . '%',
                'message' => Text::sprintf('COM_KUNENA_EMPTYTRASH_PROGRESS', $state['topics'], $state['messages']),
                'errors'  => $state['errors'],
            );
        }

        KunenaForumTrash::finish($state['affected']);

        return array(
            'message' => Text::sprintf('COM_KUNENA_EMPTYTRASH_DONE', $state['topics'], $state['messages']),
            'errors'  => $state['errors'],
        );
    }

    /**
     * Sends the JSON answer and ends the request.
     *
     * @param   array  $data  Answer
     *
     * @return  void
     *
     * @since 5.5.0
     */
    protected function respond(array $data)
    {
        while (ob_get_level() > 0 && @ob_end_clean()) {
            // Discard whatever Joomla or a plugin printed before the answer
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data + array('success' => true, 'done' => true, 'errors' => array()));
        jexit();
    }
}

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
 * Threads waiting for Baldrick (table #__kunena_baldrick_queue).
 *
 * Posting never waits for an AI provider: a post only adds a row here, and the rows are processed after the page has
 * been sent, by a background request or by the command line (kunena:baldrick:process).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaBaldrickQueue
{
    /**
     * How long a claimed job is locked (seconds)
     *
     * @since 5.3.0
     */
    public const LOCK = 600;

    /**
     * Attempts before a job fails for good
     *
     * @since 5.3.0
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * Adds a thread, unless it is already waiting.
     *
     * @param   integer  $topicId    Thread
     * @param   integer  $messageId  Post which triggered it
     * @param   string   $reason     new or revived
     * @param   string   $siteUrl    Site URL (for links in e-mails sent from the command line)
     *
     * @return  boolean  Added
     *
     * @since 5.3.0
     */
    public static function add($topicId, $messageId, $reason, $siteUrl = '')
    {
        $db = Factory::getDbo();

        $waiting = (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_baldrick_queue'))
                ->where($db->quoteName('topic_id') . ' = ' . (int) $topicId)
                ->where($db->quoteName('status') . ' IN (' . $db->quote('pending') . ', ' . $db->quote('processing') . ')')
        )->loadResult();

        if ($waiting) {
            // The thread waits already: it is answered from its newest post (a job still pending moves on to it)
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__kunena_baldrick_queue'))
                    ->set($db->quoteName('message_id') . ' = ' . (int) $messageId)
                    ->set($db->quoteName('reason') . ' = ' . $db->quote((string) $reason))
                    ->where($db->quoteName('topic_id') . ' = ' . (int) $topicId)
                    ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
                    ->where($db->quoteName('message_id') . ' < ' . (int) $messageId)
            )->execute();

            return false;
        }

        $now = Factory::getDate()->toUnix();
        $row = (object) array(
            'topic_id'     => (int) $topicId,
            'message_id'   => (int) $messageId,
            'reason'       => (string) $reason,
            'status'       => 'pending',
            'attempts'     => 0,
            'created'      => $now,
            'run_after'    => $now,
            'locked_until' => 0,
            'site_url'     => substr((string) $siteUrl, 0, 255),
            'result'       => '',
        );

        return (bool) $db->insertObject('#__kunena_baldrick_queue', $row);
    }

    /**
     * @return  integer  Jobs waiting to run now
     *
     * @since 5.3.0
     */
    public static function countDue()
    {
        $db  = Factory::getDbo();
        $now = Factory::getDate()->toUnix();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_baldrick_queue'))
                ->where('((' . $db->quoteName('status') . ' = ' . $db->quote('pending') . ' AND ' . $db->quoteName('run_after') . ' <= ' . $now . ')'
                    . ' OR (' . $db->quoteName('status') . ' = ' . $db->quote('processing') . ' AND ' . $db->quoteName('locked_until') . ' < ' . $now . '))')
        )->loadResult();
    }

    /**
     * @param   string  $status  Status
     *
     * @return  integer
     *
     * @since 5.3.0
     */
    public static function countStatus($status)
    {
        $db = Factory::getDbo();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_baldrick_queue'))
                ->where($db->quoteName('status') . ' = ' . $db->quote($status))
        )->loadResult();
    }

    /**
     * Claims the next due job (safe against parallel workers).
     *
     * @return  object|null
     *
     * @since 5.3.0
     */
    public static function claim()
    {
        $db  = Factory::getDbo();
        $now = Factory::getDate()->toUnix();

        for ($i = 0; $i < 5; $i++) {
            $job = $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__kunena_baldrick_queue'))
                    ->where('((' . $db->quoteName('status') . ' = ' . $db->quote('pending') . ' AND ' . $db->quoteName('run_after') . ' <= ' . $now . ')'
                        . ' OR (' . $db->quoteName('status') . ' = ' . $db->quote('processing') . ' AND ' . $db->quoteName('locked_until') . ' < ' . $now . '))')
                    ->order($db->quoteName('run_after') . ' ASC, ' . $db->quoteName('id') . ' ASC'),
                0,
                1
            )->loadObject();

            if (!$job) {
                return null;
            }

            // Only one worker wins the update
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__kunena_baldrick_queue'))
                    ->set($db->quoteName('status') . ' = ' . $db->quote('processing'))
                    ->set($db->quoteName('locked_until') . ' = ' . ($now + static::LOCK))
                    ->set($db->quoteName('attempts') . ' = ' . ((int) $job->attempts + 1))
                    ->where($db->quoteName('id') . ' = ' . (int) $job->id)
                    ->where($db->quoteName('status') . ' = ' . $db->quote($job->status))
                    ->where($db->quoteName('locked_until') . ' = ' . (int) $job->locked_until)
            )->execute();

            if ($db->getAffectedRows() === 1) {
                // Still "processing" after its lock expired: the worker which claimed it stopped without finishing
                $job->stalled      = $job->status === 'processing';
                $job->attempts     = (int) $job->attempts + 1;
                $job->status       = 'processing';
                $job->locked_until = $now + static::LOCK;

                return $job;
            }
        }

        return null;
    }

    /**
     * Makes the jobs claimed by a worker due now, as if their lock had expired (a worker which stopped without finishing),
     * with their attempts counted again from the start: someone is watching this run.
     *
     * @return  integer  Jobs unlocked
     *
     * @since 5.3.0
     */
    public static function unlock()
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_baldrick_queue'))
                ->set($db->quoteName('locked_until') . ' = 0')
                ->set($db->quoteName('attempts') . ' = 0')
                ->where($db->quoteName('status') . ' = ' . $db->quote('processing'))
        )->execute();

        return (int) $db->getAffectedRows();
    }

    /**
     * Ends a job.
     *
     * @param   object  $job     Job
     * @param   string  $status  done, skipped or failed
     * @param   string  $result  Short description
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function finish($job, $status, $result)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_baldrick_queue'))
                ->set($db->quoteName('status') . ' = ' . $db->quote($status))
                ->set($db->quoteName('locked_until') . ' = 0')
                ->set($db->quoteName('result') . ' = ' . $db->quote(StringHelper::substr((string) $result, 0, 250)))
                ->where($db->quoteName('id') . ' = ' . (int) $job->id)
        )->execute();
    }

    /**
     * Schedules a job again after a temporary error, or fails it after the last attempt.
     *
     * @param   object   $job     Job
     * @param   string   $result  Error
     * @param   integer  $after   Seconds the provider asked to wait (0: Baldrick's own back-off)
     *
     * @return  boolean  Will be retried
     *
     * @since 5.3.0
     */
    public static function retry($job, $result, $after = 0)
    {
        if ((int) $job->attempts >= static::MAX_ATTEMPTS) {
            static::finish($job, 'failed', $result);

            return false;
        }

        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_baldrick_queue'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('pending'))
                ->set($db->quoteName('locked_until') . ' = 0')
                ->set($db->quoteName('run_after') . ' = ' . (Factory::getDate()->toUnix() + min(86400, max((int) $after, 120 * (int) pow(3, (int) $job->attempts - 1)))))
                ->set($db->quoteName('result') . ' = ' . $db->quote(StringHelper::substr((string) $result, 0, 250)))
                ->where($db->quoteName('id') . ' = ' . (int) $job->id)
        )->execute();

        return true;
    }

    /**
     * Puts a job back without counting the attempt (Baldrick is paused).
     *
     * @param   object   $job     Job
     * @param   integer  $until   Not before (UNIX time)
     * @param   string   $result  Why
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function defer($job, $until, $result)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_baldrick_queue'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('pending'))
                ->set($db->quoteName('locked_until') . ' = 0')
                ->set($db->quoteName('attempts') . ' = ' . max(0, (int) $job->attempts - 1))
                ->set($db->quoteName('run_after') . ' = ' . (int) $until)
                ->set($db->quoteName('result') . ' = ' . $db->quote(StringHelper::substr((string) $result, 0, 250)))
                ->where($db->quoteName('id') . ' = ' . (int) $job->id)
        )->execute();
    }

    /**
     * Puts the failed jobs back in the queue (after the cause was fixed), or removes them.
     *
     * @param   boolean  $retry  true: retry them, false: discard them
     *
     * @return  integer  Jobs affected
     *
     * @since 5.3.0
     */
    public static function failed($retry)
    {
        $db    = Factory::getDbo();
        $now   = Factory::getDate()->toUnix();
        $query = $retry
            ? $db->getQuery(true)
                ->update($db->quoteName('#__kunena_baldrick_queue'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('pending'))
                ->set($db->quoteName('attempts') . ' = 0')
                ->set($db->quoteName('locked_until') . ' = 0')
                ->set($db->quoteName('run_after') . ' = ' . $now)
            : $db->getQuery(true)->delete($db->quoteName('#__kunena_baldrick_queue'));

        $query->where($db->quoteName('status') . ' = ' . $db->quote('failed'));

        if ($retry) {
            // The waiting time counts again from now (the maximum wait in the queue)
            $query->set($db->quoteName('created') . ' = ' . $now);
        }

        $db->setQuery($query)->execute();

        return (int) $db->getAffectedRows();
    }

    /**
     * Makes the jobs put back during a pause due now.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function release()
    {
        $db  = Factory::getDbo();
        $now = Factory::getDate()->toUnix();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__kunena_baldrick_queue'))
                ->set($db->quoteName('run_after') . ' = ' . $now)
                ->where($db->quoteName('status') . ' = ' . $db->quote('pending'))
                ->where($db->quoteName('run_after') . ' > ' . $now)
                ->where($db->quoteName('result') . ' LIKE ' . $db->quote('Paused: %'))
        )->execute();
    }

    /**
     * Removes finished jobs older than the given number of days.
     *
     * @param   integer  $days  Days
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function cleanup($days = 30)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__kunena_baldrick_queue'))
                ->where($db->quoteName('status') . ' IN (' . $db->quote('done') . ', ' . $db->quote('skipped') . ', ' . $db->quote('failed') . ')')
                ->where($db->quoteName('created') . ' < ' . (Factory::getDate()->toUnix() - max(1, (int) $days) * 86400))
        )->execute();
    }
}

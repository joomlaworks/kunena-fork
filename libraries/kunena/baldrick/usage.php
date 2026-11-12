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
 * Baldrick's activity log (#__kunena_baldrick_log, one row per handled thread) and monthly totals
 * (#__kunena_baldrick_usage: tokens and cost per month and model; the outcomes per month in the row with model '').
 * Monthly totals are kept for 36 months, log rows for the configured number of days.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaBaldrickUsage
{
    /**
     * Months of totals kept
     *
     * @since 5.3.0
     */
    public const MONTHS = 36;

    /**
     * Outcome counters of the usage table
     *
     * @var array  action => column
     * @since 5.3.0
     */
    public static $outcomes = array(
        'replied'           => 'replies',
        'canned'            => 'canned',
        'info'              => 'info_requests',
        'spam'              => 'spam',
        'spam_unpublished'  => 'spam',
        'skipped'           => 'skipped',
        'error'             => 'errors',
    );

    /**
     * Adds the tokens and cost of one API request to the month's totals.
     *
     * @param   string   $model  Model
     * @param   array    $usage  input, output, cache_read, cache_write
     * @param   integer  $cost   Millionths of a USD
     * @param   boolean  $error  The request failed (counted with the thread's outcome, not here)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function addRequest($model, array $usage, $cost, $error = false)
    {
        $usage += array('input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0);

        static::increment(StringHelper::substr((string) $model, 0, 150), array(
            'requests'           => 1,
            'input_tokens'       => (int) $usage['input'],
            'output_tokens'      => (int) $usage['output'],
            'cache_read_tokens'  => (int) $usage['cache_read'],
            'cache_write_tokens' => (int) $usage['cache_write'],
            'cost'               => (int) $cost,
        ));
    }

    /**
     * Counts an outcome in the month's totals.
     *
     * @param   string  $action  Action (see $outcomes)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function addOutcome($action)
    {
        if (isset(static::$outcomes[$action])) {
            static::increment('', array(static::$outcomes[$action] => 1));
        }
    }

    /**
     * @param   string  $model   Model ('' for the outcome counters)
     * @param   array   $values  column => amount
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function increment($model, array $values)
    {
        $db    = Factory::getDbo();
        $month = Factory::getDate()->format('Y-m');
        $where = array(
            $db->quoteName('month') . ' = ' . $db->quote($month),
            $db->quoteName('model') . ' = ' . $db->quote($model),
        );

        $update = $db->getQuery(true)->update($db->quoteName('#__kunena_baldrick_usage'))->where($where);

        foreach ($values as $column => $amount) {
            $update->set($db->quoteName($column) . ' = ' . $db->quoteName($column) . ' + ' . (int) $amount);
        }

        for ($i = 0; $i < 2; $i++) {
            $db->setQuery($update)->execute();

            if ($db->getAffectedRows() > 0) {
                return;
            }

            // First request of the month: create the row (another request may have just done it, then update again)
            $db->setQuery(KunenaDatabaseHelper::insertIgnore('#__kunena_baldrick_usage', array('month', 'model'), 'VALUES (' . $db->quote($month) . ', ' . $db->quote($model) . ')'))->execute();
        }
    }

    /**
     * Writes a log row.
     *
     * @param   array  $row  topic_id, message_id, reply_id, verdict, action, models, input_tokens, output_tokens,
     *                       cache_tokens, cost, duration, dry_run, message
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function log(array $row)
    {
        $row += array(
            'topic_id'      => 0,
            'message_id'    => 0,
            'reply_id'      => 0,
            'verdict'       => '',
            'action'        => '',
            'models'        => '',
            'input_tokens'  => 0,
            'output_tokens' => 0,
            'cache_tokens'  => 0,
            'cost'          => 0,
            'duration'      => 0,
            'dry_run'       => 0,
            'message'       => '',
        );

        $row['time']    = Factory::getDate()->toUnix();
        $row['models']  = StringHelper::substr((string) $row['models'], 0, 255);
        $row['verdict'] = substr((string) $row['verdict'], 0, 20);
        $row['action']  = substr((string) $row['action'], 0, 30);
        $row['message'] = StringHelper::substr((string) $row['message'], 0, 2000);

        $object = (object) $row;
        Factory::getDbo()->insertObject('#__kunena_baldrick_log', $object);

        static::addOutcome($row['action']);
    }

    /**
     * @return  integer  Replies (answers, canned replies, requests for details) in the last 24 hours
     *
     * @since 5.3.0
     */
    public static function countRecentReplies()
    {
        $db = Factory::getDbo();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_baldrick_log'))
                ->where($db->quoteName('action') . ' IN (' . $db->quote('replied') . ', ' . $db->quote('canned') . ', ' . $db->quote('info') . ')')
                ->where($db->quoteName('time') . ' > ' . (Factory::getDate()->toUnix() - 86400))
        )->loadResult();
    }

    /**
     * Totals of all kept months.
     *
     * @return  object
     *
     * @since 5.3.0
     */
    public static function getTotals()
    {
        $db = Factory::getDbo();

        $row = $db->setQuery(
            $db->getQuery(true)
                ->select(static::sumColumns($db))
                ->from($db->quoteName('#__kunena_baldrick_usage'))
        )->loadObject();

        return static::normalise($row);
    }

    /**
     * Totals per month, newest first.
     *
     * @return  object[]
     *
     * @since 5.3.0
     */
    public static function getMonths()
    {
        $db = Factory::getDbo();

        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('month'))
                ->select(static::sumColumns($db))
                ->from($db->quoteName('#__kunena_baldrick_usage'))
                ->group($db->quoteName('month'))
                ->order($db->quoteName('month') . ' DESC')
        )->loadObjectList();

        return array_map(array(__CLASS__, 'normalise'), (array) $rows);
    }

    /**
     * Totals per model of all kept months.
     *
     * @return  object[]
     *
     * @since 5.3.0
     */
    public static function getModels()
    {
        $db = Factory::getDbo();

        $rows = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('model'))
                ->select(static::sumColumns($db))
                ->from($db->quoteName('#__kunena_baldrick_usage'))
                ->where($db->quoteName('model') . ' != ' . $db->quote(''))
                ->group($db->quoteName('model'))
                ->order($db->quoteName('model') . ' ASC')
        )->loadObjectList();

        return array_map(array(__CLASS__, 'normalise'), (array) $rows);
    }

    /**
     * @param   JDatabaseDriver  $db  Database
     *
     * @return  string[]
     *
     * @since 5.3.0
     */
    protected static function sumColumns($db)
    {
        $columns = array('requests', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cost', 'errors', 'replies', 'canned', 'info_requests', 'spam', 'skipped');
        $select  = array();

        foreach ($columns as $column) {
            $select[] = 'SUM(' . $db->quoteName($column) . ') AS ' . $db->quoteName($column);
        }

        return $select;
    }

    /**
     * @param   object|null  $row  Row
     *
     * @return  object  Row with integer counters
     *
     * @since 5.3.0
     */
    public static function normalise($row)
    {
        $row = $row ? $row : new stdClass();

        foreach (array('requests', 'input_tokens', 'output_tokens', 'cache_read_tokens', 'cache_write_tokens', 'cost', 'errors', 'replies', 'canned', 'info_requests', 'spam', 'skipped') as $column) {
            $row->$column = isset($row->$column) ? (int) $row->$column : 0;
        }

        return $row;
    }

    /**
     * Log rows, newest first.
     *
     * @param   integer  $offset  Offset
     * @param   integer  $limit   Limit
     *
     * @return  object[]
     *
     * @since 5.3.0
     */
    public static function getLog($offset = 0, $limit = 50)
    {
        $db = Factory::getDbo();

        return (array) $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__kunena_baldrick_log'))
                ->order($db->quoteName('id') . ' DESC'),
            max(0, (int) $offset),
            max(1, (int) $limit)
        )->loadObjectList();
    }

    /**
     * @return  integer
     *
     * @since 5.3.0
     */
    public static function countLog()
    {
        $db = Factory::getDbo();

        return (int) $db->setQuery($db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__kunena_baldrick_log')))->loadResult();
    }

    /**
     * Removes old log rows and months.
     *
     * @param   integer  $logDays  Days of log rows to keep
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function cleanup($logDays = 90)
    {
        $db = Factory::getDbo();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__kunena_baldrick_log'))
                ->where($db->quoteName('time') . ' < ' . (Factory::getDate()->toUnix() - max(1, (int) $logDays) * 86400))
        )->execute();

        // The current month and the 35 before it
        $oldest = Factory::getDate('first day of this month')->modify('-' . (static::MONTHS - 1) . ' months')->format('Y-m');

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__kunena_baldrick_usage'))
                ->where($db->quoteName('month') . ' < ' . $db->quote($oldest))
        )->execute();
    }

    /**
     * Formats a cost.
     *
     * @param   integer  $micros  Millionths of a USD
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function formatCost($micros)
    {
        $usd = (int) $micros / 1000000;

        return '$' . number_format($usd, $usd > 0 && $usd < 1 ? 4 : 2);
    }
}

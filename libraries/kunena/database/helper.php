<?php

/**
 * Kunena Component
 * @package       Kunena.Framework
 * @subpackage    Database
 *
 * @copyright     Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;

/**
 * SQL which differs between the databases of Joomla 3.x (3.9–3.18, incl. Joomla 3.x UTD): MySQL/MariaDB, PostgreSQL and
 * SQLite. SQLite (UTD's "mysqlonsqlite" driver) runs MySQL's SQL through an emulation layer, so it gets MySQL's SQL here;
 * only PostgreSQL needs SQL of its own. Kunena's queries use backtick-quoted names, which UTD's PostgreSQL drivers turn
 * into PostgreSQL's quoted names.
 *
 * @since 5.3.0
 */
abstract class KunenaDatabaseHelper
{
    /**
     * Whether the database is PostgreSQL.
     *
     * @param   JDatabaseDriver|null $db Database (the site's by default)
     *
     * @return boolean
     *
     * @since 5.3.0
     */
    public static function isPostgresql($db = null)
    {
        $db = $db ? $db : Factory::getDbo();

        return $db->getServerType() === 'postgresql';
    }

    /**
     * Whether the database is SQLite (UTD's "mysqlonsqlite" driver: MySQL's SQL through an emulation layer, which lacks
     * a few MySQL extensions, e.g. DELETE of several tables, DELETE ... JOIN and user variables).
     *
     * @param   JDatabaseDriver|null $db Database (the site's by default)
     *
     * @return boolean
     *
     * @since 5.3.0
     */
    public static function isSqlite($db = null)
    {
        $db = $db ? $db : Factory::getDbo();

        return $db->getName() === 'mysqlonsqlite';
    }

    /**
     * Whether the database is a real MySQL/MariaDB server (not PostgreSQL, nor SQLite's MySQL emulation).
     *
     * @param   JDatabaseDriver|null $db Database (the site's by default)
     *
     * @return boolean
     *
     * @since 5.3.0
     */
    public static function isMysql($db = null)
    {
        $db = $db ? $db : Factory::getDbo();

        return $db->getServerType() === 'mysql' && !static::isSqlite($db);
    }

    /**
     * Picks the statement written for the database: array('mysql' => ..., 'postgresql' => ..., 'sqlite' => ...).
     * SQLite gets MySQL's statement unless it has one of its own; MySQL's is also the fallback for any other database.
     *
     * @param   array                $sql Statements by database
     * @param   JDatabaseDriver|null $db  Database (the site's by default)
     *
     * @return mixed
     *
     * @since 5.3.0
     */
    public static function choose(array $sql, $db = null)
    {
        $db   = $db ? $db : Factory::getDbo();
        $type = static::isSqlite($db) ? 'sqlite' : $db->getServerType();

        return isset($sql[$type]) ? $sql[$type] : $sql['mysql'];
    }

    /**
     * INSERT which skips the rows whose unique keys already exist (MySQL's INSERT IGNORE).
     *
     * @param   string               $table   Table name (e.g. #__kunena_aliases)
     * @param   array                $columns Column names
     * @param   string               $rows    VALUES (...) or SELECT ... giving the rows
     * @param   JDatabaseDriver|null $db      Database (the site's by default)
     *
     * @return string
     *
     * @since 5.3.0
     */
    public static function insertIgnore($table, array $columns, $rows, $db = null)
    {
        $db  = $db ? $db : Factory::getDbo();
        $sql = 'INTO ' . $db->quoteName($table) . ' (' . implode(', ', array_map(array($db, 'quoteName'), $columns)) . ') ' . $rows;

        if (static::isPostgresql($db)) {
            return 'INSERT ' . $sql . ' ON CONFLICT DO NOTHING';
        }

        return 'INSERT IGNORE ' . $sql;
    }

    /**
     * INSERT which updates the existing row when a unique key exists (MySQL's ON DUPLICATE KEY UPDATE, PostgreSQL's ON
     * CONFLICT DO UPDATE).
     *
     * @param   string               $table   Table name (e.g. #__kunena_users)
     * @param   array                $columns Column names
     * @param   string               $rows    VALUES (...) or SELECT ... giving the rows (each key once)
     * @param   array                $keys    Columns of the unique key (PostgreSQL's conflict target)
     * @param   array                $update  Columns to update from the new row
     * @param   JDatabaseDriver|null $db      Database (the site's by default)
     *
     * @return string
     *
     * @since 5.3.0
     */
    public static function upsert($table, array $columns, $rows, array $keys, array $update, $db = null)
    {
        $db  = $db ? $db : Factory::getDbo();
        $sql = 'INSERT INTO ' . $db->quoteName($table) . ' (' . implode(', ', array_map(array($db, 'quoteName'), $columns)) . ') ' . $rows;
        $set = array();

        if (static::isPostgresql($db)) {
            foreach ($update as $column) {
                $set[] = $db->quoteName($column) . ' = EXCLUDED.' . $db->quoteName($column);
            }

            return $sql . ' ON CONFLICT (' . implode(', ', array_map(array($db, 'quoteName'), $keys)) . ') DO '
                . ($set ? 'UPDATE SET ' . implode(', ', $set) : 'NOTHING');
        }

        foreach ($update as $column) {
            $set[] = $db->quoteName($column) . ' = VALUES(' . $db->quoteName($column) . ')';
        }

        return $sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $set);
    }

    /**
     * A UNIX timestamp column or expression as a date and time (MySQL's FROM_UNIXTIME()).
     *
     * @param   string               $expression SQL expression
     * @param   JDatabaseDriver|null $db         Database (the site's by default)
     *
     * @return string
     *
     * @since 5.3.0
     */
    public static function fromUnixtime($expression, $db = null)
    {
        if (static::isPostgresql($db)) {
            return 'to_timestamp(' . $expression . ')::timestamp';
        }

        return 'FROM_UNIXTIME(' . $expression . ')';
    }

    /**
     * Columns of a table as getTableColumns($table, false) gives them (PostgreSQL drivers of Joomla 3.x UTD shape them as
     * MySQL's SHOW COLUMNS: Field, Type, Null, Default, Extra), cached per request.
     *
     * @param   string               $table Table name (e.g. #__kunena_topics)
     * @param   JDatabaseDriver|null $db    Database (the site's by default)
     *
     * @return array
     *
     * @since 5.3.0
     */
    public static function getColumns($table, $db = null)
    {
        static $columns = array();

        $db  = $db ? $db : Factory::getDbo();
        $key = $db->getServerType() . ':' . $db->replacePrefix($table);

        if (!isset($columns[$key])) {
            $columns[$key] = (array) $db->getTableColumns($table, false);
        }

        return $columns[$key];
    }

    /**
     * Whether a column takes its values from a sequence (MySQL's auto_increment, PostgreSQL's serial).
     *
     * @param   string               $table  Table name (e.g. #__kunena_topics)
     * @param   string               $column Column name
     * @param   JDatabaseDriver|null $db     Database (the site's by default)
     *
     * @return boolean
     *
     * @since 5.3.0
     */
    public static function isAutoIncrement($table, $column, $db = null)
    {
        $columns = static::getColumns($table, $db);

        return isset($columns[$column]->Extra) && stripos($columns[$column]->Extra, 'auto_increment') !== false;
    }

    /**
     * A value quoted for a column. On PostgreSQL, the values MySQL (without strict mode) converts on its own are converted
     * as MySQL does: '' and booleans in number columns become numbers, '' and zero dates in date columns become Kunena's
     * "no date" (or NULL, where allowed). Other databases get $db->quote($value).
     *
     * @param   string               $table  Table name (e.g. #__kunena_topics)
     * @param   string               $column Column name
     * @param   mixed                $value  Scalar value
     * @param   JDatabaseDriver|null $db     Database (the site's by default)
     *
     * @return string
     *
     * @since 5.3.0
     */
    public static function quoteValue($table, $column, $value, $db = null)
    {
        $db = $db ? $db : Factory::getDbo();

        if (!static::isPostgresql($db)) {
            return $db->quote($value);
        }

        $columns = static::getColumns($table, $db);
        $type    = isset($columns[$column]->Type) ? strtolower($columns[$column]->Type) : '';

        if (preg_match('/^(smallint|integer|bigint|numeric|real|double)/', $type)) {
            if (is_bool($value)) {
                $value = (int) $value;
            } elseif (!is_numeric($value)) {
                $value = preg_match('/^\s*[-+]?\d+(\.\d+)?/', (string) $value, $match) ? $match[0] : 0;
            }

            return $db->quote((string) $value);
        }

        if (preg_match('/^(timestamp|date)/', $type) && ($value === '' || $value === false || preg_match('/^0000-00-00/', (string) $value))) {
            if (isset($columns[$column]->Null) && strtoupper($columns[$column]->Null) === 'YES') {
                return 'NULL';
            }

            return $db->quote(strpos($type, 'timestamp') === 0 ? '1000-01-01 00:00:00' : '1000-01-01');
        }

        if (is_bool($value)) {
            $value = (int) $value;
        }

        return $db->quote((string) $value);
    }

    /**
     * Case-insensitive LIKE operator (PostgreSQL's LIKE is case-sensitive, MySQL's collations aren't).
     *
     * @param   JDatabaseDriver|null $db Database (the site's by default)
     *
     * @return string
     *
     * @since 5.3.0
     */
    public static function like($db = null)
    {
        return static::isPostgresql($db) ? 'ILIKE' : 'LIKE';
    }
}

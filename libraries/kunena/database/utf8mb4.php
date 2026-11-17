<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Database
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;

/**
 * Converts Kunena's MySQL tables to utf8mb4 (Kunena > Maintenance, kunena:database:utf8mb4).
 *
 * Kunena's installer only ever changed its tables' *default* character set to utf8mb4, so the columns of sites
 * installed before utf8mb4 (utf8 = utf8mb3) were never converted: posts can't hold emoji or other 4-byte characters,
 * and a LIKE with such a character (a search) fails with "Illegal mix of collations (utf8mb3_general_ci,IMPLICIT) and
 * (utf8mb4_..., COERCIBLE)". This converts the tables whose columns aren't utf8mb4 yet (CONVERT TO CHARACTER SET:
 * every text column and the table's default), one table at a time.
 *
 * MySQL and MariaDB only; PostgreSQL and SQLite store UTF-8 as it is.
 *
 * @since 5.5.0
 */
#[\AllowDynamicProperties]
abstract class KunenaDatabaseUtf8mb4
{
    /**
     * The collation of the converted tables (Joomla's own)
     *
     * @since 5.5.0
     */
    public const COLLATION = 'utf8mb4_unicode_ci';

    /**
     * @return  boolean  The database is MySQL or MariaDB with utf8mb4
     *
     * @since 5.5.0
     */
    public static function applies()
    {
        $db = Factory::getDbo();

        return KunenaDatabaseHelper::isMysql($db) && method_exists($db, 'hasUTF8mb4Support') && $db->hasUTF8mb4Support();
    }

    /**
     * @return  array  Kunena's tables to convert: name => (object) rows (estimate), columns (not utf8mb4), collation
     *
     * @since 5.5.0
     */
    public static function pending()
    {
        if (!static::applies()) {
            return array();
        }

        $db      = Factory::getDbo();
        $pattern = $db->quote(str_replace('_', '\\_', $db->getPrefix() . 'kunena_') . '%');
        $tables  = $db->setQuery(
            'SELECT t.TABLE_NAME AS tname, t.TABLE_ROWS AS trows, t.TABLE_COLLATION AS tcollation,'
            . ' (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME'
            . " AND c.CHARACTER_SET_NAME IS NOT NULL AND c.CHARACTER_SET_NAME <> 'utf8mb4') AS tcolumns"
            . ' FROM information_schema.TABLES t'
            . " WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_TYPE = 'BASE TABLE' AND t.TABLE_NAME LIKE " . $pattern
            . ' ORDER BY t.TABLE_NAME'
        )->loadObjectList('tname');
        $pending = array();

        foreach ($tables as $name => $table) {
            if ((int) $table->tcolumns || stripos((string) $table->tcollation, 'utf8mb4') !== 0) {
                $pending[$name] = (object) array('rows' => (int) $table->trows, 'columns' => (int) $table->tcolumns, 'collation' => (string) $table->tcollation);
            }
        }

        return $pending;
    }

    /**
     * Converts one table (only one that pending() lists).
     *
     * @param   string  $table  Table name (with the site's prefix)
     *
     * @return  void
     *
     * @throws  RuntimeException  Not a table to convert, or MySQL refused (e.g. an index too long for the server)
     *
     * @since 5.5.0
     */
    public static function convert($table)
    {
        if (!isset(static::pending()[$table])) {
            throw new RuntimeException(sprintf('%s is not a Kunena table waiting for conversion.', $table));
        }

        $db = Factory::getDbo();
        $db->setQuery('ALTER TABLE ' . $db->quoteName($table) . ' CONVERT TO CHARACTER SET utf8mb4 COLLATE ' . static::COLLATION)->execute();
    }
}

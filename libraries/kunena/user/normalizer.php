<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    User
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * The rules for usernames and names (Kunena > Configuration > Members), and the normalisation of existing accounts
 * (Kunena > Tools > Normalise Usernames & Names, and kunena:users:normalize):
 *
 * - usernames: ASCII letters, digits and dashes (user_names_strict);
 * - names: letters of any alphabet, digits, spaces and . , ' ’ - & ( ) (user_names_strict);
 * - both start with a letter or a digit (user_names_leading).
 *
 * New accounts and changed usernames/names are checked by the System - Kunena plugin (onUserBeforeSave): registration,
 * profile edits, the administrator's user manager. Normalisation rewrites the accounts which break the rules in
 * #__users directly (no Joomla events: passwords and everything else are left alone).
 *
 * @since 5.5.0
 */
#[\AllowDynamicProperties]
abstract class KunenaUserNormalizer
{
    /**
     * Accounts read at once
     *
     * @since 5.5.0
     */
    public const BATCH = 500;

    /**
     * Joomla's limits for usernames (#__users.username)
     *
     * @since 5.5.0
     */
    public const MIN_USERNAME = 2;

    public const MAX_USERNAME = 150;

    /**
     * Joomla's limit for names (#__users.name)
     *
     * @since 5.5.0
     */
    public const MAX_NAME = 400;

    /**
     * @return  boolean  Usernames and names are restricted to common characters
     *
     * @since 5.5.0
     */
    public static function isStrict()
    {
        return (bool) KunenaConfig::getInstance()->user_names_strict;
    }

    /**
     * @return  boolean  Usernames and names start with a letter or a digit
     *
     * @since 5.5.0
     */
    public static function isLeading()
    {
        return (bool) KunenaConfig::getInstance()->user_names_leading;
    }

    /**
     * @return  boolean  Any rule is on
     *
     * @since 5.5.0
     */
    public static function isEnabled()
    {
        return static::isStrict() || static::isLeading();
    }

    /**
     * @param   string  $username  Username
     *
     * @return  string|null  Why the username breaks the rules (translated), or null
     *
     * @since 5.5.0
     */
    public static function usernameError($username)
    {
        $username = (string) $username;

        if (static::isStrict() && !preg_match('/^[A-Za-z0-9-]+$/', $username)) {
            return Text::_('COM_KUNENA_USERNAMES_ERROR_USERNAME_CHARS');
        }

        if (static::isLeading() && !preg_match('/^[\p{L}\p{N}]/u', $username)) {
            return Text::_('COM_KUNENA_USERNAMES_ERROR_USERNAME_LEADING');
        }

        return null;
    }

    /**
     * @param   string  $name  Name
     *
     * @return  string|null  Why the name breaks the rules (translated), or null
     *
     * @since 5.5.0
     */
    public static function nameError($name)
    {
        $name = (string) $name;

        if (static::isStrict() && !preg_match('/^[\p{L}\p{M}\p{N} .,\'\x{2019}&()-]+$/u', $name)) {
            return Text::_('COM_KUNENA_USERNAMES_ERROR_NAME_CHARS');
        }

        if (static::isLeading() && !preg_match('/^[\p{L}\p{N}]/u', $name)) {
            return Text::_('COM_KUNENA_USERNAMES_ERROR_NAME_LEADING');
        }

        return null;
    }

    /**
     * A username which follows the rules, made from the current one (not checked for uniqueness: see unique()).
     *
     * "Fotis_Evangelou" -> "Fotis-Evangelou", "007.david@gmail.com" -> "007-david-gmail-com", "Φώτης" -> "Fotis"
     * (where PHP's intl or iconv can transliterate), "__x__" -> "x-<id>" (too short), "@@@" -> "user-<id>".
     *
     * @param   string   $username  Current username
     * @param   integer  $userid    The account's id (for usernames which would be empty or too short)
     *
     * @return  string
     *
     * @since 5.5.0
     */
    public static function normalizeUsername($username, $userid)
    {
        $username = trim((string) $username);

        if (static::isStrict()) {
            $username = trim(preg_replace('/[^A-Za-z0-9]+/', '-', static::ascii($username)), '-');
        }

        if (static::isLeading()) {
            $username = preg_replace('/^[^\p{L}\p{N}]+/u', '', $username);
        }

        if ($username === '') {
            return 'user-' . (int) $userid;
        }

        if (static::length($username) < static::MIN_USERNAME) {
            $username .= '-' . (int) $userid;
        }

        return static::cut($username, static::MAX_USERNAME);
    }

    /**
     * A name which follows the rules, made from the current one: other characters become spaces ("Daniel Correa ✔" ->
     * "Daniel Correa", "_tMF_" -> "tMF").
     *
     * @param   string  $name      Current name
     * @param   string  $fallback  Name to use if nothing is left (the account's username)
     *
     * @return  string
     *
     * @since 5.5.0
     */
    public static function normalizeName($name, $fallback)
    {
        $name = (string) $name;

        if (static::isStrict()) {
            $name = preg_replace('/[^\p{L}\p{M}\p{N} .,\'\x{2019}&()-]+/u', ' ', $name);
            $name = trim(preg_replace('/\s+/u', ' ', (string) $name));
            $name = rtrim($name, " ,&-(");
        }

        if (static::isLeading()) {
            $name = preg_replace('/^[^\p{L}\p{N}]+/u', '', trim($name));
        }

        $name = trim((string) $name);

        if ($name === '') {
            $name = (string) $fallback;
        }

        return static::cut($name, static::MAX_NAME);
    }

    /**
     * @param   string   $username  Wanted username
     * @param   integer  $userid    The account's id
     *
     * @return  string  The username, or one with "-<id>" ("-<id>-2"...) added when another account has it (compared
     *                  as Joomla's login does on MySQL: without case)
     *
     * @since 5.5.0
     */
    public static function unique($username, $userid)
    {
        $candidate = $username;

        for ($i = 1; static::taken($candidate, $userid); $i++) {
            $suffix    = '-' . (int) $userid . ($i > 1 ? '-' . $i : '');
            $candidate = static::cut($username, static::MAX_USERNAME - strlen($suffix)) . $suffix;
        }

        return $candidate;
    }

    /**
     * Finds the accounts that break the rules.
     *
     * @param   integer  $limit  Stop after this many (0: all)
     * @param   integer  $after  Start after this account id
     *
     * @return  array  changes (id => [username, name, newUsername, newName], newUsername/newName null when unchanged),
     *                 last (last id read; 0 when every account was read)
     *
     * @since 5.5.0
     */
    public static function scan($limit = 0, $after = 0)
    {
        $db      = Factory::getDbo();
        $changes = array();
        $lastId  = (int) $after;

        if (!static::isEnabled()) {
            return array('changes' => array(), 'last' => 0);
        }

        while (true) {
            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(array('id', 'username', 'name')))
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('id') . ' > ' . $lastId)
                    ->order($db->quoteName('id')),
                0,
                static::BATCH
            )->loadObjectList();

            if (!$rows) {
                return array('changes' => $changes, 'last' => 0);
            }

            foreach ($rows as $row) {
                $lastId      = (int) $row->id;
                $newUsername = static::usernameError($row->username) === null ? null : static::normalizeUsername($row->username, $row->id);
                $newName     = static::nameError($row->name) === null ? null
                    : static::normalizeName($row->name, $newUsername !== null ? $newUsername : $row->username);

                if ($newUsername === $row->username) {
                    $newUsername = null;
                }

                if ($newName === $row->name) {
                    $newName = null;
                }

                if ($newUsername !== null || $newName !== null) {
                    $changes[$lastId] = array((string) $row->username, (string) $row->name, $newUsername, $newName);

                    if ($limit && count($changes) >= $limit) {
                        return array('changes' => $changes, 'last' => $lastId);
                    }
                }
            }
        }
    }

    /**
     * @return  integer[]  accounts, usernames and names breaking the rules
     *
     * @since 5.5.0
     */
    public static function count()
    {
        $result = array('accounts' => 0, 'usernames' => 0, 'names' => 0);

        foreach (static::scan()['changes'] as $change) {
            $result['accounts']++;
            $result['usernames'] += $change[2] !== null ? 1 : 0;
            $result['names'] += $change[3] !== null ? 1 : 0;
        }

        return $result;
    }

    /**
     * Normalises the accounts after $after, up to $limit of them.
     *
     * @param   integer  $limit   Accounts to change in this step
     * @param   integer  $after   Start after this account id (the previous step's "last")
     * @param   boolean  $dryRun  Only compute the changes (unique usernames included)
     *
     * @return  array  changed (id => [username, name, newUsername, newName]), last (0: done)
     *
     * @since 5.5.0
     */
    public static function normalize($limit, $after = 0, $dryRun = false)
    {
        $db      = Factory::getDbo();
        $scan    = static::scan($limit, $after);
        $changed = array();

        foreach ($scan['changes'] as $id => $change) {
            list($username, $name, $newUsername, $newName) = $change;

            if ($newUsername !== null) {
                $newUsername = static::unique($newUsername, $id);
            }

            $changed[$id] = array($username, $name, $newUsername, $newName);

            if ($dryRun) {
                continue;
            }

            $fields = array();

            if ($newUsername !== null) {
                $fields[] = $db->quoteName('username') . ' = ' . $db->quote($newUsername);
            }

            if ($newName !== null) {
                $fields[] = $db->quoteName('name') . ' = ' . $db->quote($newName);
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__users'))
                    ->set($fields)
                    ->where($db->quoteName('id') . ' = ' . (int) $id)
            )->execute();

            // Who's online shows the session's copy of the username
            if ($newUsername !== null) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__session'))
                        ->set($db->quoteName('username') . ' = ' . $db->quote($newUsername))
                        ->where($db->quoteName('userid') . ' = ' . (int) $id)
                )->execute();
            }
        }

        return array('changed' => $changed, 'last' => $scan['last']);
    }

    /**
     * @param   string   $username  Username
     * @param   integer  $userid    The account which wants it
     *
     * @return  boolean  Another account has it
     *
     * @since 5.5.0
     */
    protected static function taken($username, $userid)
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' <> ' . (int) $userid);

        // MySQL compares usernames without case (collation, and the index is used); the others need LOWER()
        if (KunenaDatabaseHelper::isMysql()) {
            $query->where($db->quoteName('username') . ' = ' . $db->quote($username));
        } else {
            $query->where('LOWER(' . $db->quoteName('username') . ') = ' . $db->quote(strtolower($username)));
        }

        return (int) $db->setQuery($query)->loadResult() > 0;
    }

    /**
     * @param   string  $text  Text
     *
     * @return  string  The text in ASCII letters where it can be transliterated (other characters stay as they are, to
     *                  become dashes)
     *
     * @since 5.5.0
     */
    protected static function ascii($text)
    {
        if (preg_match('/^[\x00-\x7F]*$/', $text)) {
            return $text;
        }

        if (class_exists('Transliterator')) {
            // Greek as Greeks write it in Latin letters (ELOT 743 / UN): "Φώτης" -> "Fotis", not "Photes"
            $transliterator = Transliterator::create('Greek-Latin/UNGEGN; Any-Latin; Latin-ASCII') ?: Transliterator::create('Any-Latin; Latin-ASCII');

            if ($transliterator) {
                $result = $transliterator->transliterate($text);

                if (is_string($result)) {
                    return $result;
                }
            }
        }

        if (function_exists('iconv')) {
            $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

            if (is_string($result) && $result !== '') {
                return $result;
            }
        }

        return \Joomla\CMS\Language\Transliterate::utf8_latin_to_ascii($text);
    }

    /**
     * @param   string  $text  Text
     *
     * @return  integer  Characters
     *
     * @since 5.5.0
     */
    protected static function length($text)
    {
        return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : (int) preg_match_all('/./us', $text);
    }

    /**
     * @param   string   $text  Text
     * @param   integer  $max   Characters
     *
     * @return  string  The text cut to $max characters
     *
     * @since 5.5.0
     */
    protected static function cut($text, $max)
    {
        if (static::length($text) <= $max) {
            return $text;
        }

        return function_exists('mb_substr') ? mb_substr($text, 0, $max, 'UTF-8') : substr($text, 0, $max);
    }
}

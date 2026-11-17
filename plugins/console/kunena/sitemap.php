<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Plugin
 * @subpackage    Console.Kunena
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * XML sitemaps of the forum, written as static files into media/kunena/sitemaps/ by the command line (the Console - Kunena
 * plugin's kunena:sitemap:generate, e.g. from cron); everything else lives in this plugin, Kunena itself is unchanged:
 *
 * - sitemap_index.xml: the sitemap index, listing the files below;
 * - categories.xml: every category guests can read;
 * - threads-YYYY.xml: the threads started in that year (threads-YYYY-2.xml... past 50,000 URLs).
 *
 * Only what a guest sees is listed: published threads of categories guests can read, without the shadows of moved
 * threads; each thread by its first page. The URLs are the site's own SEF URLs: the site application is made the current
 * one while they are built, so Kunena's router and menu items make them exactly as on the site. Submit the index
 * through robots.txt ("Sitemap: <index URL>") and Google Search Console: a sitemap only covers URLs below its own folder
 * otherwise.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class PlgConsoleKunenaSitemap
{
    /**
     * Folder of the files, from the site's root
     *
     * @since 5.3.0
     */
    public const FOLDER = 'media/kunena/sitemaps';

    /**
     * URLs per file (the sitemaps protocol's limit)
     *
     * @since 5.3.0
     */
    public const MAX_URLS = 50000;

    /**
     * Threads loaded at once while their URLs are built
     *
     * @since 5.3.0
     */
    public const BATCH = 500;

    /**
     * @return  string  The site's address (Joomla's live_site, which the command line's --live-site sets), without a
     *                  trailing slash; '' when there is none
     *
     * @since 5.3.0
     */
    public static function getSiteUrl()
    {
        $url = rtrim(trim((string) Factory::getConfig()->get('live_site')), '/');

        return preg_match('#^https?://[^/]+#i', $url) ? $url : '';
    }

    /**
     * @return  string  Absolute URL of the sitemap index
     *
     * @since 5.3.0
     */
    public static function getIndexUrl()
    {
        return static::getSiteUrl() . '/' . static::FOLDER . '/sitemap_index.xml';
    }

    /**
     * What would be written: the files and their number of URLs.
     *
     * @return  array  file name => URLs
     *
     * @since 5.3.0
     */
    public static function plan()
    {
        $files = array('categories.xml' => count(static::getCategories()));

        foreach (static::getThreadCounts() as $year => $count) {
            for ($part = 1; ($part - 1) * static::MAX_URLS < $count; $part++) {
                $files[static::threadFile($year, $part)] = min(static::MAX_URLS, $count - ($part - 1) * static::MAX_URLS);
            }
        }

        return $files;
    }

    /**
     * Writes the sitemaps.
     *
     * @return  array  files (file name => URLs), urls (total), removed (stale files deleted), seconds
     *
     * @throws  RuntimeException  No site address, or the folder can't be written
     *
     * @since 5.3.0
     */
    public static function generate()
    {
        $start = microtime(true);
        $site  = static::getSiteUrl();

        if ($site === '') {
            throw new RuntimeException('The site\'s address is unknown: set live_site in configuration.php, or pass --live-site=https://www.example.com');
        }

        $folder = JPATH_ROOT . '/' . static::FOLDER;

        if (!is_dir($folder) && !@mkdir($folder, 0755, true)) {
            throw new RuntimeException('The folder ' . static::FOLDER . ' can\'t be created.');
        }

        $written = array();
        $index   = array();
        $state   = static::enterSite($site);

        try {
            // Categories
            $out = static::openUrlset('categories.xml');

            foreach (static::getCategories() as $category) {
                static::addUrl($out, static::url('index.php?option=com_kunena&view=category&catid=' . (int) $category->id, $site), (int) $category->last_post_time);
            }

            list($written['categories.xml'], $index['categories.xml']) = static::closeUrlset($out);

            // Threads, by the year they were started: each file is written as its URLs are made (memory stays flat)
            foreach (static::getThreadCounts() as $year => $count) {
                $part = 1;
                $file = static::threadFile($year, $part);
                $out  = static::openUrlset($file);

                foreach (static::getThreadBatches($year) as $rows) {
                    KunenaForumTopicHelper::getTopics(array_keys($rows), 'none');

                    foreach ($rows as $row) {
                        if ($out['count'] === static::MAX_URLS) {
                            list($written[$file], $index[$file]) = static::closeUrlset($out);
                            $file                                = static::threadFile($year, ++$part);
                            $out                                 = static::openUrlset($file);
                        }

                        static::addUrl($out, static::url('index.php?option=com_kunena&view=topic&catid=' . (int) $row->category_id . '&id=' . (int) $row->id, $site), (int) $row->last_post_time);
                    }

                    // Long runs: neither the threads nor their routes and slugs are needed again
                    static::forget('KunenaForumTopicHelper', '_instances', array_keys($rows));
                    static::forget('KunenaRoute', 'filtered');
                    static::forgetRoutes();
                    KunenaRoute::$uris = array();
                }

                list($written[$file], $index[$file]) = static::closeUrlset($out);
            }
        } finally {
            if (isset($out['handle']) && is_resource($out['handle'])) {
                fclose($out['handle']);
                @unlink($out['temp']);
            }

            static::leaveSite($state);
        }

        static::writeIndex($index, $site);

        // Files of earlier runs which no longer have threads (e.g. a year whose threads were all deleted)
        $removed = array();

        foreach ((array) glob($folder . '/*.xml') as $path) {
            $name = basename($path);

            if (preg_match('/^(categories|threads-\d{4}(-\d+)?)\.xml$/', $name) && !isset($written[$name]) && @unlink($path)) {
                $removed[] = $name;
            }
        }

        return array(
            'files'   => $written,
            'urls'    => array_sum($written),
            'removed' => $removed,
            'seconds' => round(microtime(true) - $start, 1),
        );
    }

    /**
     * @return  object[]  Published categories guests can read (id, last_post_time), in the forum's order
     *
     * @since 5.3.0
     */
    protected static function getCategories()
    {
        $ids = KunenaForumCategoryHelper::getGuestReadable();

        if (!$ids) {
            return array();
        }

        $db = Factory::getDbo();

        return (array) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(array('id', 'last_post_time')))
                ->from($db->quoteName('#__kunena_categories'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')')
                ->order($db->quoteName('ordering') . ', ' . $db->quoteName('id'))
        )->loadObjectList();
    }

    /**
     * The conditions of the listed threads: published, not the shadow of a moved thread, in a category guests can read.
     *
     * @param   JDatabaseQuery  $query  Query (topics as t)
     *
     * @return  JDatabaseQuery|false  The query, false when no category is readable by guests
     *
     * @since 5.3.0
     */
    protected static function threadConditions($query)
    {
        $db  = Factory::getDbo();
        $ids = KunenaForumCategoryHelper::getGuestReadable();

        if (!$ids) {
            return false;
        }

        return $query
            ->from($db->quoteName('#__kunena_topics', 't'))
            ->where($db->quoteName('t.hold') . ' = 0')
            ->where($db->quoteName('t.moved_id') . ' = 0')
            ->where($db->quoteName('t.category_id') . ' IN (' . implode(',', array_map('intval', $ids)) . ')');
    }

    /**
     * @return  integer[]  year => threads started that year (UTC), newest year first
     *
     * @since 5.3.0
     */
    protected static function getThreadCounts()
    {
        $db    = Factory::getDbo();
        $query = static::threadConditions($db->getQuery(true));

        if (!$query) {
            return array();
        }

        $range = $db->setQuery(
            $query->select('MIN(' . $db->quoteName('t.first_post_time') . ') AS ' . $db->quoteName('first'))
                ->select('MAX(' . $db->quoteName('t.first_post_time') . ') AS ' . $db->quoteName('last'))
        )->loadObject();

        $counts = array();

        if (!$range || !$range->last) {
            return $counts;
        }

        $first = (int) gmdate('Y', max(0, (int) $range->first));
        $last  = (int) gmdate('Y', (int) $range->last);

        for ($year = $last; $year >= $first; $year--) {
            list($from, $to) = static::yearRange($year);
            $count           = (int) $db->setQuery(
                static::threadConditions($db->getQuery(true))
                    ->select('COUNT(*)')
                    ->where($db->quoteName('t.first_post_time') . ' >= ' . $from)
                    ->where($db->quoteName('t.first_post_time') . ' < ' . $to)
            )->loadResult();

            if ($count) {
                $counts[$year] = $count;
            }
        }

        return $counts;
    }

    /**
     * The threads started in a year, in batches (oldest first): id => (id, category_id, last_post_time).
     *
     * @param   integer  $year  Year (UTC)
     *
     * @return  Generator
     *
     * @since 5.3.0
     */
    protected static function getThreadBatches($year)
    {
        $db             = Factory::getDbo();
        list($from, $to) = static::yearRange($year);
        $lastId         = 0;

        while (true) {
            $rows = $db->setQuery(
                static::threadConditions($db->getQuery(true))
                    ->select($db->quoteName(array('t.id', 't.category_id', 't.last_post_time')))
                    ->where($db->quoteName('t.first_post_time') . ' >= ' . $from)
                    ->where($db->quoteName('t.first_post_time') . ' < ' . $to)
                    ->where($db->quoteName('t.id') . ' > ' . (int) $lastId)
                    ->order($db->quoteName('t.id')),
                0,
                static::BATCH
            )->loadObjectList('id');

            if (!$rows) {
                return;
            }

            $lastId = (int) max(array_keys($rows));

            yield $rows;
        }
    }

    /**
     * @param   integer  $year  Year
     *
     * @return  integer[]  First second of the year, first second of the next one (UTC)
     *
     * @since 5.3.0
     */
    protected static function yearRange($year)
    {
        return array(gmmktime(0, 0, 0, 1, 1, (int) $year), gmmktime(0, 0, 0, 1, 1, (int) $year + 1));
    }

    /**
     * @param   integer  $year  Year
     * @param   integer  $part  Part (1, 2... past MAX_URLS threads)
     *
     * @return  string  File name
     *
     * @since 5.3.0
     */
    protected static function threadFile($year, $part)
    {
        return 'threads-' . (int) $year . ($part > 1 ? '-' . (int) $part : '') . '.xml';
    }

    /**
     * Makes the site application the current one, as for a request to the site's home page, so that Kunena's routing
     * builds the site's SEF URLs (with its menu items) instead of the command line's plain links.
     *
     * @param   string  $site  The site's address
     *
     * @return  array  What leaveSite() restores
     *
     * @since 5.3.0
     */
    protected static function enterSite($site)
    {
        $state = array('app' => Factory::$application, 'server' => $_SERVER);
        $path  = rtrim((string) parse_url($site, PHP_URL_PATH), '/');
        $port  = parse_url($site, PHP_URL_PORT);

        // The request of the site's home page (on the command line these name the CLI script)
        $_SERVER['HTTP_HOST']       = parse_url($site, PHP_URL_HOST) . ($port ? ':' . $port : '');
        $_SERVER['SERVER_NAME']     = (string) parse_url($site, PHP_URL_HOST);
        $_SERVER['SCRIPT_NAME']     = $path . '/index.php';
        $_SERVER['PHP_SELF']        = $path . '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = JPATH_ROOT . '/index.php';
        $_SERVER['REQUEST_URI']     = $path . '/';
        $_SERVER['QUERY_STRING']    = '';
        $_SERVER['REQUEST_METHOD']  = 'GET';

        if (strtolower((string) parse_url($site, PHP_URL_SCHEME)) === 'https') {
            $_SERVER['HTTPS'] = 'on';
        } else {
            unset($_SERVER['HTTPS']);
        }

        Uri::reset();

        Factory::$application = \Joomla\CMS\Application\CMSApplication::getInstance('site');

        static::resetRouting();

        return $state;
    }

    /**
     * Restores the command line's application and request.
     *
     * @param   array  $state  enterSite()'s result
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function leaveSite(array $state)
    {
        Factory::$application = $state['app'];
        $_SERVER              = $state['server'];

        Uri::reset();
        static::resetRouting();
    }

    /**
     * Sets Kunena's routing up again for the current application (its state is public): the site's menus and SEF URLs
     * for the site application, plain links for the command line's.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function resetRouting()
    {
        KunenaRoute::$adminApp  = false;
        KunenaRoute::$menus     = false;
        KunenaRoute::$menu      = false;
        KunenaRoute::$default   = false;
        KunenaRoute::$active    = null;
        KunenaRoute::$home      = false;
        KunenaRoute::$search    = false;
        KunenaRoute::$current   = null;
        KunenaRoute::$childlist = false;
        KunenaRoute::$subtree   = array();
        KunenaRoute::$parent    = array();
        KunenaRoute::$uris      = array();
        KunenaRoute::$urisSave  = false;

        KunenaRoute::initialize();
    }

    /**
     * Empties the site router's cache of the URLs it built (Joomla's Router keeps a copy of every one).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function forgetRoutes()
    {
        try {
            $router     = \Joomla\CMS\Router\Router::getInstance('site');
            $reflection = new ReflectionProperty('Joomla\\CMS\\Router\\Router', 'cache');

            // Needed before PHP 8.1 only (deprecated in PHP 8.5)
            if (PHP_VERSION_ID < 80100) {
                $reflection->setAccessible(true);
            }

            $reflection->setValue($router, array());
        } catch (Exception $e) {
            // No such cache in this Joomla version: nothing to empty
        }
    }

    /**
     * Empties (some keys of) a static cache of Kunena's (protected), so that going through a large forum in batches doesn't
     * keep every thread, route and slug in memory.
     *
     * @param   string          $class     Class
     * @param   string          $property  Static property (an array)
     * @param   integer[]|null  $keys      Keys to remove (null: all)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function forget($class, $property, ?array $keys = null)
    {
        static $properties = array();

        if (!isset($properties[$class . '::' . $property])) {
            try {
                $reflection = new ReflectionProperty($class, $property);

                // Needed before PHP 8.1 only (deprecated in PHP 8.5)
                if (PHP_VERSION_ID < 80100) {
                    $reflection->setAccessible(true);
                }
            } catch (ReflectionException $e) {
                $reflection = false;
            }

            $properties[$class . '::' . $property] = $reflection;
        }

        $reflection = $properties[$class . '::' . $property];

        if (!$reflection) {
            return;
        }

        $values = array();

        if ($keys !== null) {
            $values = (array) $reflection->getValue();

            foreach ($keys as $key) {
                unset($values[$key]);
            }
        }

        $reflection->setValue(null, $values);
    }

    /**
     * @param   string  $link  Kunena link (index.php?option=com_kunena...)
     * @param   string  $site  The site's address
     *
     * @return  string  Absolute SEF URL, with non-ASCII characters and spaces percent-encoded
     *
     * @since 5.3.0
     */
    protected static function url($link, $site)
    {
        $url = (string) KunenaRoute::_($link, false);

        if (!preg_match('#^https?://#i', $url)) {
            $url = preg_replace('#^(https?://[^/]+).*$#i', '$1', $site) . '/' . ltrim($url, '/');
        }

        return preg_replace_callback('/[^\x21-\x7E]/', function ($m) {
            return rawurlencode($m[0]);
        }, $url);
    }

    /**
     * Starts a sitemap (urlset): a temporary file, renamed into place when complete (crawlers never read a half-written
     * one).
     *
     * @param   string  $file  File name
     *
     * @return  array  file, path, temp, handle, count, newest
     *
     * @throws  RuntimeException
     *
     * @since 5.3.0
     */
    protected static function openUrlset($file)
    {
        $path   = JPATH_ROOT . '/' . static::FOLDER . '/' . $file;
        $temp   = $path . '.' . getmypid() . '.tmp';
        $handle = @fopen($temp, 'wb');

        if (!$handle) {
            throw new RuntimeException('The file ' . static::FOLDER . '/' . $file . ' can\'t be written.');
        }

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n");

        return array('file' => $file, 'path' => $path, 'temp' => $temp, 'handle' => $handle, 'count' => 0, 'newest' => 0);
    }

    /**
     * @param   array    $out   openUrlset()'s result
     * @param   string   $url   URL
     * @param   integer  $time  Last modification (UNIX time; 0: unknown)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function addUrl(array &$out, $url, $time)
    {
        fwrite($out['handle'], '<url><loc>' . static::escape($url) . '</loc>' . ($time > 0 ? '<lastmod>' . gmdate('c', $time) . '</lastmod>' : '') . "</url>\n");

        $out['count']++;
        $out['newest'] = max($out['newest'], (int) $time);
    }

    /**
     * Completes a sitemap and puts it into place.
     *
     * @param   array  $out  openUrlset()'s result
     *
     * @return  integer[]  URLs, newest modification time
     *
     * @throws  RuntimeException
     *
     * @since 5.3.0
     */
    protected static function closeUrlset(array &$out)
    {
        fwrite($out['handle'], '</urlset>' . "\n" . '<!-- ' . $out['count'] . ' URLs, generated ' . gmdate('Y-m-d H:i:s') . ' UTC -->' . "\n");
        $ok = fclose($out['handle']);

        $out['handle'] = null;

        if (!$ok || !@rename($out['temp'], $out['path'])) {
            @unlink($out['temp']);

            throw new RuntimeException('The file ' . static::FOLDER . '/' . $out['file'] . ' can\'t be written.');
        }

        @chmod($out['path'], 0644);

        return array($out['count'], $out['newest']);
    }

    /**
     * Writes the sitemap index.
     *
     * @param   integer[]  $files  file name => newest modification time
     * @param   string     $site   The site's address
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function writeIndex(array $files, $site)
    {
        $xml = array(
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        );

        foreach ($files as $file => $newest) {
            $xml[] = '<sitemap><loc>' . static::escape($site . '/' . static::FOLDER . '/' . $file) . '</loc>'
                . ($newest > 0 ? '<lastmod>' . gmdate('c', $newest) . '</lastmod>' : '') . '</sitemap>';
        }

        $xml[] = '</sitemapindex>';
        $xml[] = '<!-- ' . count($files) . ' sitemaps, generated ' . gmdate('Y-m-d H:i:s') . ' UTC -->';

        static::write('sitemap_index.xml', implode("\n", $xml) . "\n");
    }

    /**
     * Writes a file atomically: crawlers never read a half-written one.
     *
     * @param   string  $file     File name
     * @param   string  $content  Content
     *
     * @return  void
     *
     * @throws  RuntimeException
     *
     * @since 5.3.0
     */
    protected static function write($file, $content)
    {
        $path = JPATH_ROOT . '/' . static::FOLDER . '/' . $file;
        $temp = $path . '.' . getmypid() . '.tmp';

        if (@file_put_contents($temp, $content) === false || !@rename($temp, $path)) {
            @unlink($temp);

            throw new RuntimeException('The file ' . static::FOLDER . '/' . $file . ' can\'t be written.');
        }

        @chmod($path, 0644);
    }

    /**
     * @param   string  $text  Text
     *
     * @return  string  XML-escaped
     *
     * @since 5.3.0
     */
    protected static function escape($text)
    {
        return htmlspecialchars((string) $text, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    }
}

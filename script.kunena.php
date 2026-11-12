<?php

/**
 * Kunena Component
 *
 * Unified installer script. Kunena used to be distributed as a Joomla! "package"
 * (pkg_kunena) wrapping five separate archives. Since 5.3.0 the whole forum is
 * shipped as a single component package (K2-style), which can be installed
 * directly from the ZIP that GitHub exports for the repository. This script
 * installs the bundled library and plugins, migrates the legacy package
 * records and then hands over to Kunena's own installation wizard.
 *
 * @package        Kunena.Installer
 *
 * @copyright      Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license        https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link           https://www.kunena.org
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Table;

/**
 * Class Com_KunenaInstallerScript
 *
 * @since Kunena
 */
#[\AllowDynamicProperties]
class Com_KunenaInstallerScript
{
    /**
     * List of supported versions. Newest version first!
     *
     * @var array
     * @since Kunena
     */
    protected $versions = [
        'PHP'     => [
            '7.1' => '7.1.0',
            '0'   => '7.4.0', // Preferred version
        ],
        'MySQL'   => [
            '5.7' => '5.7.8',
            '5.6' => '5.6.5',
            '5.5' => '5.5.3',
            '0'   => '5.5.3', // Preferred version
        ],
        'PostgreSQL' => [
            '9.5' => '9.5.0',
            '0'   => '9.5.0', // Preferred version
        ],
        'SQLite'  => [
            '3.37' => '3.37.0',
            '0'    => '3.37.0', // Preferred version
        ],
        'Joomla!' => [
            '3.9' => '3.9.0',
            '0'   => '3.18.0', // Preferred version (Joomla 3.x UTD)
        ],
    ];

    /**
     * List of required PHP extensions.
     *
     * @var array
     * @since Kunena
     */
    protected $extensions = ['dom', 'gd', 'json', 'pcre', 'SimpleXML', 'mbstring'];

    /**
     * Extensions bundled in this package which are installed by this script
     * (path relative to the package root => [type, group/client, element, enable]).
     *
     * enable: 'always' - enabled on every installation and update (Kunena needs them);
     *         'new'    - enabled only when installed for the first time: an existing one keeps its state;
     *         'never'  - left as installed (modules: their instances are placed and published by the site owner).
     *
     * @var array
     * @since 5.3.0
     */
    protected $bundled = [
        'libraries/kunena'              => ['library', '', 'kunena', 'never'],
        'plugins/system/kunena'         => ['plugin', 'system', 'kunena', 'always'],
        'plugins/quickicon/kunena'      => ['plugin', 'quickicon', 'kunena', 'always'],
        // Joomla 3.x UTD command line commands (harmless elsewhere)
        'plugins/console/kunena'        => ['plugin', 'console', 'kunena', 'always'],
        // The extensions kunena.org used to distribute separately
        'plugins/content/kunenadiscuss' => ['plugin', 'content', 'kunenadiscuss', 'new'],
        'plugins/search/kunena'         => ['plugin', 'search', 'kunena', 'new'],
        // Baldrick AI: enabled on first installation (it does nothing until an API key is set)
        'plugins/kunena/baldrick'       => ['plugin', 'kunena', 'baldrick', 'new'],
        'modules/mod_kunenalatest'      => ['module', 'site', 'mod_kunenalatest', 'never'],
        'modules/mod_kunenalogin'       => ['module', 'site', 'mod_kunenalogin', 'never'],
        'modules/mod_kunenasearch'      => ['module', 'site', 'mod_kunenasearch', 'never'],
        'modules/mod_kunenastats'       => ['module', 'site', 'mod_kunenastats', 'never'],
    ];

    /**
     * @param   object  $parent  parent
     *
     * @return boolean
     * @since Kunena
     */
    public function install($parent)
    {
        // Delete all cached files.
        $cacheDir = JPATH_CACHE . '/kunena';

        if (is_dir($cacheDir)) {
            Folder::delete($cacheDir);
        }

        Folder::create($cacheDir);

        return true;
    }

    /**
     * @param   object  $parent  parent
     *
     * @return boolean
     * @since Kunena
     */
    public function discover_install($parent)
    {
        return $this->install($parent);
    }

    /**
     * @param   object  $parent  parent
     *
     * @return boolean
     * @since Kunena
     */
    public function update($parent)
    {
        return $this->install($parent);
    }

    /**
     * @param   object  $parent  parent
     *
     * @return boolean
     * @since Kunena
     * @throws Exception
     */
    public function uninstall($parent)
    {
        $adminpath = $parent->getParent()->getPath('extension_administrator');
        $model     = "{$adminpath}/install/model.php";

        if (file_exists($model)) {
            require_once $model;
            $installer = new KunenaModelInstall();
            $installer->uninstall();
        }

        return true;
    }

    /**
     * @param   string  $type    type
     * @param   object  $parent  parent
     *
     * @return boolean
     * @since Kunena
     * @throws Exception
     */
    public function preflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        $app = Factory::getApplication();

        // Prevent to be installed on Joomla! 4.x and later
        if (version_compare(JVERSION, '4.0', '>=')) {
            $app->enqueueMessage(sprintf('Joomla! %s is not supported. Kunena 5.3 only works on Joomla! 3.x (3.9 - 3.18, including Joomla 3.x UTD)', JVERSION), 'notice');

            return false;
        }

        $installer = $parent->getParent();
        $manifest  = $installer->getManifest();

        // Prevent installation if requirements are not met.
        if (!$this->checkRequirements((string) $manifest->version)) {
            return false;
        }

        // Remove old log file before installation.
        $logFile = Factory::getConfig()->get('log_path') . '/kunena.php';

        if (is_file($logFile)) {
            @unlink($logFile);
        }

        $adminPath = $installer->getPath('extension_administrator');
        $sitePath  = $installer->getPath('extension_site');

        if (is_file($adminPath . '/admin.kunena.php')) {
            // Kunena 2.0 or older release found, clean up the directories.
            static $ignoreAdmin = ['index.html', 'kunena.xml', 'archive'];

            if (is_file($adminPath . '/install.script.php')) {
                // Kunena 1.7 or older release..
                $ignoreAdmin[] = 'install.script.php';
                $ignoreAdmin[] = 'admin.kunena.php';
            }

            static $ignoreSite = ['index.html', 'kunena.php', 'router.php', 'template', 'COPYRIGHT.php', 'CHANGELOG.php'];
            $this->deleteFolder($adminPath, $ignoreAdmin);
            $this->deleteFolder($sitePath, $ignoreSite);
        }

        // Remove Blue Eagle template on K5.0
        if (is_dir($sitePath . '/template/blue_eagle')) {
            $this->deleteKfolder($sitePath . '/template/blue_eagle');
        }

        // Delete languages files related to blue eagle in en-gb and others languages
        foreach ([$sitePath . '/language', JPATH_ROOT . '/language'] as $languageRoot) {
            if (!Folder::exists($languageRoot)) {
                continue;
            }

            foreach (Folder::folders($languageRoot) as $folder) {
                $this->deleteFile($languageRoot . '/' . $folder . '/' . $folder . '.com_kunena.tpl_blue_eagle.ini');
            }
        }

        // Delete the directories of plugins of CKeditor not present anymore
        foreach (['copyformatting', 'div', 'flashs', 'forms', 'pagebreak', 'pastetools', 'preview', 'showblocks', 'table', 'tableselection', 'tabletools', 'templates', 'wsc'] as $ckPlugin) {
            $this->deleteFolder(JPATH_SITE . '/media/kunena/core/js/plugins/' . $ckPlugin);
        }

        // Move custom files of Crypsis and CrypsisB3 into their new locations
        foreach (['crypsis', 'crypsisb3'] as $template) {
            $base = $sitePath . '/template/' . $template;

            $this->moveLegacyFile($base . '/less/custom.less', $base . '/assets/less', 'custom.less');
            $this->moveLegacyFile($base . '/css/custom.css', $base . '/assets/css', 'custom.css');
            $this->moveLegacyFile($base . '/params.ini', $base . '/config', 'params.ini');

            // Remove old template files
            if (is_file($base . '/template.xml')) {
                $this->deleteKfolder($base . '/css');
                $this->deleteKfolder($base . '/images');
                $this->deleteKfolder($base . '/less');
                $this->deleteKfolder($base . '/media');
                $this->deleteFile($base . '/config.xml');
                $this->deleteFile($base . '/kunena_tmpl_crypsis.xml');
                $this->deleteFile($base . '/template.xml');
            }

            $this->deleteFile($base . '/assets/js/markitup.editor-min.js');
        }

        // Delete completely the directory assets/js of the templates to don't keep old JS files
        foreach (['crypsis', 'crypsisb3', 'crypsisb4'] as $template) {
            if (is_file($sitePath . '/template/' . $template . '/assets/js/jssocials.js')) {
                $this->deleteKfolder($sitePath . '/template/' . $template . '/assets/js');
            }
        }

        // Delete some CSS files under old locations in /assets/css
        $oldCss = ['custom.css', 'jssocials.css', 'jssocials-theme-classic.css', 'jssocials-theme-flat.css', 'jssocials-theme-minima.css', 'jssocials-theme-plain.css', 'wbbtheme.css'];
        $this->deleteFiles($sitePath . '/template/crypsis/assets/css', array_merge($oldCss, ['fancybox.black-min.css', 'fancybox.black.css', 'fancybox.white-min.css', 'fancybox.white.css']));
        $this->deleteFiles($sitePath . '/template/crypsisb3/assets/css', array_merge($oldCss, ['fancybox.black-min.css', 'fancybox.black.css', 'fancybox.white-min.css', 'fancybox.white.css']));
        $this->deleteFiles($sitePath . '/template/crypsisb4/assets/css', $oldCss);

        // 5.3.0: dead JavaScript (WysiBB was never selectable; the other files were never loaded). One file at a time:
        // deleteFiles() empties a folder except the files it is given.
        $deadFiles = [
            JPATH_SITE . '/media/kunena/core/js/jquery.wysibb.js',
            JPATH_SITE . '/media/kunena/core/js/wysibb.lang.js',
            JPATH_SITE . '/media/kunena/core/js/jquery.fileupload-ui.js',
            JPATH_SITE . '/media/kunena/core/js/build-config.js',
            JPATH_SITE . '/media/kunena/core/css/wbbtheme.css',
            $sitePath . '/template/crypsisb4/assets/js/jquery.caret.js',
            $sitePath . '/template/crypsisb4/assets/js/jquery.caret-min.js',
        ];

        // 5.3.0: integrations no longer shipped (the installer uninstalls the plugins themselves)
        $deadFiles[] = $sitePath . '/jomsocial_rule.xml';

        foreach (['alphauserpoints', 'altauserpoints', 'community', 'easyblog', 'easyprofile', 'easysocial', 'uddeim'] as $integration) {
            $deadFiles[] = JPATH_ADMINISTRATOR . '/components/com_kunena/language/en-GB/en-GB.plg_kunena_' . $integration . '.sys.ini';
        }

        foreach (['crypsis', 'crypsisb3', 'crypsisb4'] as $template) {
            $deadFiles[] = $sitePath . '/template/' . $template . '/layouts/widget/editor/wysibb.php';
            $deadFiles[] = $sitePath . '/template/' . $template . '/layouts/widget/editor/wysibb_quick.php';
        }

        foreach ($deadFiles as $deadFile) {
            $this->deleteFile($deadFile);
        }

        // Remove old system directory
        if (is_file(JPATH_ROOT . '/media/kunena/topic_icons/system/topicicons.xml')) {
            if (!is_file(JPATH_ROOT . '/media/kunena/archive/topic_icons/system/topicicons.xml')) {
                Folder::create(JPATH_ROOT . '/media/kunena/archive/topic_icons');
                Folder::copy(JPATH_ROOT . '/media/kunena/topic_icons/system', JPATH_ROOT . '/media/kunena/archive/topic_icons/system');
                Folder::delete(JPATH_ROOT . '/media/kunena/topic_icons/system');
            }

            if (!is_file(JPATH_ROOT . '/media/kunena/topic_icons/systemold/topicicons.xml')) {
                Folder::create(JPATH_ROOT . '/media/kunena/topic_icons/systemold');
                File::copy(JPATH_ROOT . '/media/kunena/topic_icons/default/topicicons.xml', JPATH_ROOT . '/media/kunena/topic_icons/systemold/topicicons.xml');
            }

            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__kunena_categories'))
                ->set($db->quoteName('iconset') . ' = ' . $db->quote('default'))
                ->where($db->quoteName('iconset') . ' = ' . $db->quote('system'));
            $db->setQuery($query)->execute();
        }

        // K5.1 Remove files
        if (is_file(JPATH_ROOT . '/administrator/components/com_kunena/template/plugin/edit.php')) {
            $this->deleteKfolder(JPATH_ROOT . '/administrator/components/com_kunena/template/plugin');
            $this->deleteKfolder(JPATH_ROOT . '/administrator/components/com_kunena/views/plugin');
            $this->deleteFile(JPATH_ROOT . '/administrator/components/com_kunena/controllers/plugin.php');
        }

        $this->deleteFile(JPATH_ROOT . '/media/kunena/js/debug.js');

        if (is_file(JPATH_ROOT . '/libraries/kunena/compat/joomla/image/image.php')) {
            $this->deleteFile(JPATH_ROOT . '/libraries/kunena/compat/joomla/image/image.php');
            $this->deleteKfolder(JPATH_ROOT . '/components/com_kunena/template/crypsis/layouts/topic/edit/editor');
            $this->deleteKfolder(JPATH_ROOT . '/components/com_kunena/template/crypsisb3/layouts/topic/edit/editor');
            $this->deleteFile(JPATH_ROOT . '/components/com_kunena/layout/topic/edit/editor.php');
        }

        // K5.3: the old per-component installer script has been replaced by script.kunena.php
        $this->deleteFile($adminPath . '/install/install.script.php');

        // Prepare installation.
        $model = "{$adminPath}/install/model.php";

        if (file_exists($model)) {
            require_once $model;
            $kinstaller = new KunenaModelInstall();
            $kinstaller->install();
        }

        return true;
    }

    /**
     * @param   string  $type    type
     * @param   object  $parent  parent
     *
     * @return boolean
     * @since Kunena
     * @throws Exception
     */
    public function postflight($type, $parent)
    {
        if ($type === 'uninstall') {
            return true;
        }

        $installer = $parent->getParent();

        // Install the library and plugins bundled in this package.
        $this->installBundledExtensions($installer->getPath('source'));

        // Migrate from the legacy "pkg_kunena" + "files_kunena_media" layout.
        $this->removeLegacyPackage();

        if (version_compare(JVERSION, '4.0', '<')) {
            $this->convertTablesToUtf8mb4();
        }

        // Clear Joomla system cache.
        Factory::getCache()->clean('_system');

        // Remove all compiled files from opcode caches.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        // Continue with Kunena's own installation wizard (database migration etc).
        // Only in the administrator: the command line (cli/joomla.php) has no router and no browser to redirect.
        $app = Factory::getApplication();

        if (method_exists($app, 'isClient') && $app->isClient('administrator')) {
            $installer->set('redirect_url', Route::_('index.php?option=com_kunena&view=install', false));
        }

        return true;
    }

    /**
     * Install the library and plugins which are shipped inside this package.
     *
     * @param   string  $source  Path to the extracted package.
     *
     * @return void
     * @since 5.3.0
     * @throws Exception
     */
    protected function installBundledExtensions($source)
    {
        $app = Factory::getApplication();

        foreach ($this->bundled as $path => $info) {
            list($type, $group, $element, $enable) = $info;

            $dir = $source . '/' . $path;

            // Discover install or installation from an incomplete package: nothing to do.
            if (!is_dir($dir)) {
                continue;
            }

            // State before the installation: null when the extension isn't installed yet
            $enabledBefore = $type === 'plugin' ? $this->getPluginState($group, $element) : null;

            $tmpInstaller = new Installer();

            if (!$tmpInstaller->install($dir)) {
                $app->enqueueMessage(sprintf('Kunena: failed to install the bundled %s "%s".', $type, trim($group . '/' . $element, '/')), 'error');

                continue;
            }

            if ($type !== 'plugin') {
                continue;
            }

            if ($enable === 'always' || ($enable === 'new' && $enabledBefore === null)) {
                $this->enablePlugin($group, $element);
            } elseif ($enabledBefore !== null) {
                // Respect the site's choice (e.g. a disabled Kunena Discuss stays disabled)
                $this->enablePlugin($group, $element, $enabledBefore);
            }
        }

        // The predecessor of the "Search - Kunena" plugin
        $this->uninstallExtension('plugin', 'search', 'kunenasearch');
    }

    /**
     * @param   string  $group    Plugin group
     * @param   string  $element  Plugin element
     *
     * @return  integer|null  1 or 0, or null when the plugin isn't installed
     *
     * @since 5.3.0
     */
    protected function getPluginState($group, $element)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('enabled'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($group))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element))
        );
        $state = $db->loadResult();

        return $state === null ? null : (int) $state;
    }

    /**
     * @param   string  $type     Extension type
     * @param   string  $group    Plugin group
     * @param   string  $element  Element
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected function uninstallExtension($type, $group, $element)
    {
        $db = Factory::getDbo();
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($type))
                ->where($db->quoteName('folder') . ' = ' . $db->quote($group))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element))
        );
        $id = (int) $db->loadResult();

        if ($id) {
            (new Installer())->uninstall($type, $id);
        }
    }

    /**
     * Kunena <= 5.2 was installed as a package (pkg_kunena) with the media files
     * installed as a separate "file" extension (kunena_media). The media files are
     * now managed by the component itself, so drop the legacy records without
     * removing any files from the disk.
     *
     * @return void
     * @since 5.3.0
     */
    protected function removeLegacyPackage()
    {
        $db = Factory::getDbo();

        $legacy = [
            ['package', 'pkg_kunena', JPATH_MANIFESTS . '/packages/pkg_kunena.xml', JPATH_MANIFESTS . '/packages/kunena'],
            ['file', 'kunena_media', JPATH_MANIFESTS . '/files/kunena_media.xml', JPATH_MANIFESTS . '/files/kunena_media'],
        ];

        foreach ($legacy as $item) {
            list($type, $element, $manifest, $scriptFolder) = $item;

            $query = $db->getQuery(true)
                ->select($db->quoteName('extension_id'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote($type))
                ->where($db->quoteName('element') . ' = ' . $db->quote($element));
            $ids = array_map('intval', (array) $db->setQuery($query)->loadColumn());

            if ($ids) {
                $idList = implode(',', $ids);

                // Detach the former children from the package.
                $columns = $db->getTableColumns('#__extensions');

                if (isset($columns['package_id'])) {
                    $db->setQuery(
                        $db->getQuery(true)
                            ->update($db->quoteName('#__extensions'))
                            ->set($db->quoteName('package_id') . ' = 0')
                            ->where($db->quoteName('package_id') . ' IN (' . $idList . ')')
                    )->execute();
                }

                // Remove update site bindings of the legacy records.
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__update_sites_extensions'))
                        ->where($db->quoteName('extension_id') . ' IN (' . $idList . ')')
                )->execute();

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__updates'))
                        ->where($db->quoteName('extension_id') . ' IN (' . $idList . ')')
                )->execute();

                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName('#__extensions'))
                        ->where($db->quoteName('extension_id') . ' IN (' . $idList . ')')
                )->execute();
            }

            if (is_file($manifest)) {
                File::delete($manifest);
            }

            // Folder holding the legacy installer script of the package / file extension.
            if (is_dir($scriptFolder)) {
                Folder::delete($scriptFolder);
            }
        }

        // The official Kunena 5.2 update server only knows about the legacy package.
        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites'))
            ->where($db->quoteName('location') . ' LIKE ' . $db->quote('https://update.kunena.org/%'));
        $siteIds = array_map('intval', (array) $db->setQuery($query)->loadColumn());

        if ($siteIds) {
            $idList = implode(',', $siteIds);

            foreach (['#__update_sites_extensions', '#__updates', '#__update_sites'] as $table) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->delete($db->quoteName($table))
                        ->where($db->quoteName('update_site_id') . ' IN (' . $idList . ')')
                )->execute();
            }
        }
    }

    /**
     * @param   string  $group    group
     * @param   string  $element  element
     * @param   integer $state    1 enabled, 0 disabled
     *
     * @return boolean
     * @since Kunena
     */
    public function enablePlugin($group, $element, $state = 1)
    {
        $plugin = Table::getInstance('extension');

        if (!$plugin->load(['type' => 'plugin', 'folder' => $group, 'element' => $element])) {
            return false;
        }

        $plugin->enabled = (int) $state;

        return $plugin->store();
    }

    /**
     * @param   string  $version  version
     *
     * @return boolean|integer
     * @since Kunena
     * @throws Exception
     */
    public function checkRequirements($version)
    {
        $db   = Factory::getDbo();
        $pass = $this->checkVersion('PHP', $this->getCleanPhpVersion());
        $pass &= $this->checkVersion('Joomla!', JVERSION);
        $pass &= $this->checkDbo($db->name, ['mysql', 'mysqli', 'pdomysql', 'mysqlonsqlite', 'postgresql', 'pgsql']);

        // Joomla 3.x UTD: MySQL/MariaDB, PostgreSQL and SQLite (MySQL emulation), each with its own minimum version
        if ($db->getServerType() === 'postgresql') {
            $pass &= $this->checkVersion('PostgreSQL', preg_replace('/^(\d+(?:\.\d+)*).*$/s', '$1', (string) $db->getVersion()));
        } elseif ($db->name === 'mysqlonsqlite' && method_exists($db, 'getSqliteVersion')) {
            $pass &= $this->checkVersion('SQLite', $db->getSqliteVersion());
        } else {
            $pass &= $this->checkVersion('MySQL', $db->getVersion());
        }

        $pass &= $this->checkPhpExtensions($this->extensions);
        $pass &= $this->checkKunena($version);

        return $pass;
    }

    // Internal functions

    /**
     * On some hosting the PHP version given with the version of the packet in the distribution
     *
     * @return string
     * @since Kunena
     */
    protected function getCleanPhpVersion()
    {
        return PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION;
    }

    /**
     * @param   string  $name     name
     * @param   string  $version  version
     *
     * @return boolean
     * @since Kunena
     * @throws Exception
     */
    protected function checkVersion($name, $version)
    {
        $app = Factory::getApplication();

        $major = $minor = 0;

        foreach ($this->versions[$name] as $major => $minor) {
            if (!$major || version_compare($version, $major, '<')) {
                continue;
            }

            if (version_compare($version, $minor, '>=')) {
                return true;
            }

            break;
        }

        if (!$major) {
            $minor = reset($this->versions[$name]);
        }

        $recommended = end($this->versions[$name]);
        $app->enqueueMessage(sprintf('%s %s is not supported. Minimum required version is %s %s, but it is highly recommended to use %s %s or later.', $name, $version, $name, $minor, $name, $recommended), 'notice');

        return false;
    }

    /**
     * @param   string  $name   name
     * @param   array   $types  types
     *
     * @return boolean
     * @since Kunena
     * @throws Exception
     */
    protected function checkDbo($name, $types)
    {
        if (in_array($name, $types, true)) {
            return true;
        }

        Factory::getApplication()->enqueueMessage(sprintf("Database driver '%s' is not supported. Please use MySQL/MariaDB, PostgreSQL or SQLite instead.", $name), 'notice');

        return false;
    }

    /**
     * Check that the Php extensions needed for Kunena are enabled
     *
     * @param   array  $extensions  extensions
     *
     * @return integer
     * @since Kunena
     * @throws Exception
     */
    protected function checkPhpExtensions($extensions)
    {
        $app  = Factory::getApplication();
        $pass = 1;

        foreach ($extensions as $name) {
            if (!extension_loaded($name)) {
                $pass = 0;
                $app->enqueueMessage(sprintf("Required PHP extension '%s' is missing. Please install it into your system.", $name), 'notice');
            }
        }

        return $pass;
    }

    /**
     * @param   string  $version  version
     *
     * @return boolean
     * @since Kunena
     * @throws Exception
     */
    protected function checkKunena($version)
    {
        $app = Factory::getApplication();

        // Always load Kunena API if it exists.
        $api = JPATH_ADMINISTRATOR . '/components/com_kunena/api.php';

        if (is_file($api)) {
            require_once $api;
        }

        // Do not install over Git repository (K1.6+).
        if (class_exists('KunenaForum') && method_exists('KunenaForum', 'isDev') && KunenaForum::isDev()) {
            $app->enqueueMessage('Oops! You should not install Kunena over your Git repository!', 'notice');

            return false;
        }

        $db = Factory::getDbo();

        // Check if Kunena can be found from the database
        $table = $db->getPrefix() . 'kunena_version';
        if (!in_array(strtolower($table), array_map('strtolower', (array) $db->getTableList()), true)) {
            return true;
        }

        // Get installed Kunena version
        $db->setQuery('SELECT ' . $db->quoteName('version') . ' FROM ' . $db->quoteName($table) . ' ORDER BY ' . $db->quoteName('id') . ' DESC', 0, 1);
        $installed = $db->loadResult();

        if (!$installed) {
            return true;
        }

        // Always allow upgrade to the newer version
        if (version_compare($version, $installed, '>=')) {
            return true;
        }

        // Check if we can downgrade to the current version
        if (class_exists('KunenaInstaller')) {
            if (KunenaInstaller::canDowngrade($version)) {
                return true;
            }
        } else {
            // Workaround when Kunena files were removed to allow downgrade between bugfix versions.
            $major = preg_replace('/(\d+.\d+)\..*$/', '\\1', $version);

            if (version_compare($installed, $major, '>')) {
                return true;
            }
        }

        $app->enqueueMessage(sprintf('Sorry, it is not possible to downgrade Kunena %s to version %s.', $installed, $version), 'notice');

        return false;
    }

    /**
     * Move a file from a legacy location into its new folder (unless it already exists there).
     *
     * @param   string  $file      Old file
     * @param   string  $folder    New folder
     * @param   string  $filename  New file name
     *
     * @return void
     * @since 5.3.0
     */
    protected function moveLegacyFile($file, $folder, $filename)
    {
        if (!is_file($file)) {
            return;
        }

        if (!is_file($folder . '/' . $filename)) {
            Folder::create($folder);
            File::copy($file, $folder . '/' . $filename);
        }

        $this->deleteFile($file);
    }

    /**
     * @param   string  $path  path
     *
     * @return void
     * @since Kunena
     */
    public function deleteFile($path)
    {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /**
     * @param   string  $path    path
     * @param   array   $ignore  ignore
     *
     * @return void
     * @since Kunena
     */
    public function deleteFiles($path, $ignore = [])
    {
        $ignore = array_merge($ignore, ['.git', '.svn', 'CVS', '.DS_Store', '__MACOSX']);

        if (Folder::exists($path)) {
            foreach (Folder::files($path, '.', false, true, $ignore) as $file) {
                if (File::exists($file)) {
                    File::delete($file);
                }
            }
        }
    }

    /**
     * @param   string  $path    path
     * @param   array   $ignore  ignore
     *
     * @return void
     * @since Kunena
     */
    public function deleteFolders($path, $ignore = [])
    {
        $ignore = array_merge($ignore, ['.git', '.svn', 'CVS', '.DS_Store', '__MACOSX']);

        if (Folder::exists($path)) {
            foreach (Folder::folders($path, '.', false, true, $ignore) as $folder) {
                if (Folder::exists($folder)) {
                    Folder::delete($folder);
                }
            }
        }
    }

    /**
     * @param   string  $path    path
     * @param   array   $ignore  ignore
     *
     * @return void
     * @since Kunena
     */
    public function deleteFolder($path, $ignore = [])
    {
        $this->deleteFiles($path, $ignore);
        $this->deleteFolders($path, $ignore);
    }

    /**
     * @param   string  $path  path
     *
     * @return void
     * @since Kunena
     */
    public function deleteKfolder($path)
    {
        if (Folder::exists($path)) {
            Folder::delete($path);
        }
    }

    /**
     * Converts the site's database tables to support UTF-8 Multibyte.
     *
     * @return  void
     * @since   3.5
     */
    public function convertTablesToUtf8mb4()
    {
        $db = Factory::getDbo();

        // This is only required for MySQL databases (not for SQLite, which only emulates MySQL)
        if ($db->getServerType() !== 'mysql' || $db->name === 'mysqlonsqlite' || !$db->hasUTF8mb4Support()) {
            return;
        }

        // Nothing to do, saved conversion status from DB is equal to required
        if ($db->getCollation() === 'utf8mb4_unicode_ci') {
            return;
        }

        // Drop indexes later to be added again with column lengths limitations
        $fileName = JPATH_ROOT . '/administrator/components/com_kunena/install/sql/migrate/mysql/utf8mb4-conversion.sql';

        if (!is_file($fileName)) {
            return;
        }

        $queries = $db->splitSql((string) file_get_contents($fileName));

        foreach ($queries as $query) {
            try {
                $db->setQuery($query)->execute();
            } catch (Exception $e) {
                // If the query fails we will go on. It just means the index to be dropped does not exist.
            }
        }
    }
}

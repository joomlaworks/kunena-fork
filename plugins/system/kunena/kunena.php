<?php

/**
 * Kunena System Plugin
 *
 * @package         Kunena.Plugins
 * @subpackage      System
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Class plgSystemKunena
 * @since Kunena
 */
#[\AllowDynamicProperties]
class plgSystemKunena extends \Joomla\CMS\Plugin\CMSPlugin
{
    /**
     * @param   object $subject Subject
     * @param   array  $config  Config
     *
     * @throws Exception
     * @since Kunena
     */
    public function __construct(&$subject, $config)
    {
        // Check if Kunena API exists
        $api = JPATH_ADMINISTRATOR . '/components/com_kunena/api.php';

        if (!is_file($api)) {
            return;
        }

        jimport('joomla.application.component.helper');

        // Check if Kunena component is installed/enabled
        if (!\Joomla\CMS\Component\ComponentHelper::isEnabled('com_kunena')) {
            return;
        }

        // Load Kunena API
        require_once $api;

        // Do not load if Kunena version is not supported or Kunena is not installed
        if (!(class_exists('KunenaForum') && KunenaForum::isCompatible('4.0') && KunenaForum::installed())) {
            return;
        }

        parent::__construct($subject, $config);

        // ! Always load language after parent::construct else the name of plugin isn't yet set
        $this->loadLanguage('plg_system_kunena.sys');
    }

    /**
     * @internal
     *
     * @param   string  $context Context
     * @param   boolean $params  Params
     *
     * @since Kunena
     * @return void
     */
    public function onKunenaGetConfiguration($context, &$params)
    {
        if ($context == 'kunena.configuration') {
            $params["plg_{$this->_type}_{$this->_name}"] = $this->params;
        }
    }

    /**
     * @param   mixed   $user    User
     * @param   boolean $isnew   Is new
     * @param   boolean $success Success
     * @param   string  $msg     Message
     *
     * @return void
     * @throws Exception
     * @since Kunena
     */
    public function onUserAfterSave($user, $isnew, $success, $msg)
    {
        // Don't continue if the user wasn't stored successfully
        if (!$success) {
            return;
        }

        if ($isnew && intval($user ['id'])) {
            $kuser = KunenaFactory::getUser(intval($user ['id']));
            $kuser->save();
        }
    }

    /**
     * Prevent downgrades to Kunena 1.7 and older releases
     *
     * @param   string $method   method
     * @param   string $type     type
     * @param   string $manifest manifest
     * @param   int    $eid      id
     *
     * @return boolean|null
     * @throws Exception
     * @since Kunena
     */
    public function onExtensionBeforeInstall($method, $type, $manifest, $eid)
    {
        // We don't want to handle discover install (where there's no manifest provided)
        if (!$manifest) {
            return;
        }

        return $this->onExtensionBeforeUpdate($type, $manifest);
    }

    /**
     * Prevent downgrades to Kunena 1.7 and older releases
     *
     * @param   boolean $type     type
     * @param   string  $manifest manifest
     *
     * @return boolean
     * @throws Exception
     * @since Kunena
     */
    public function onExtensionBeforeUpdate($type, $manifest)
    {
        if ($type != 'component') {
            return true;
        }

        // Generate component name
        $name    = strtolower(\Joomla\CMS\Filter\InputFilter::getInstance()->clean((string) $manifest->name, 'cmd'));
        $element = (substr($name, 0, 4) == "com_") ? $name : "com_{$name}";

        if ($element != 'com_kunena') {
            return true;
        }

        // Kunena 2.0.0-BETA2 and later support this feature in their installer
        if (version_compare($manifest->version, '2.0.0', '>=')) {
            return true;
        }

        // Check if we can downgrade to the current version
        if (class_exists('KunenaInstaller') && KunenaInstaller::canDowngrade($manifest->version)) {
            return true;
        }

        // Old version detected: emulate failed installation
        $app = Factory::getApplication();
        $app->enqueueMessage(sprintf(
            'Sorry, it is not possible to downgrade Kunena %s to version %s.',
            KunenaForum::version(),
            $manifest->version
        ), 'warning');
        $app->enqueueMessage(Text::_('JLIB_INSTALLER_ABORT_COMP_INSTALL_CUSTOM_INSTALL_FAILURE'), 'error');
        $app->enqueueMessage(Text::sprintf('COM_INSTALLER_MSG_UPDATE_ERROR', Text::_('COM_INSTALLER_TYPE_TYPE_' . strtoupper($type))));
        $app->redirect('index.php?option=com_installer');

        return true;
    }

    /**
     * Runs all Joomla content plugins on a single KunenaForumMessage
     *
     * @access protected
     * @see    self::onKunenaPrepare()
     * @since  Kunena 2.0
     *
     * @param   string $text   String to run events on
     * @param   object $params \Joomla\Registry\Registry object holding eventual parameters
     * @param   int    $page   An integer holding page number
     *
     * @return object KunenaForumMessage
     * @throws Exception
     */
    protected function runJoomlaContentEvent(&$text, &$params, $page = 0)
    {

        \Joomla\CMS\Plugin\PluginHelper::importPlugin('content');

        $row       = new stdClass();
        $row->text = &$text;

        Factory::getApplication()->triggerEvent('onContentPrepare', array('text', &$row, &$params, 0));

        $text = &$row->text;

        return $text;
    }

    /**
     * Adds the Kunena Privacy Information to Joomla Privacy plugin.
     *
     * @return array
     *
     * @since Kunena 5.1.6
     */
    public function onPrivacyCollectAdminCapabilities()
    {
        $capabilities = array(
            'Kunena' => array(
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_EMAIL'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_IP_ADDRESS'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_USERPROFILE'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_POSTS'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_RATINGS'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_STATISTICS'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_COOKIES'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_LOGS'),
                Text::_('PLG_SYSTEM_KUNENA_PRIVACY_CAPABILITY_SOCIAL')
            ),
        );

        return $capabilities;
    }

    /**
     * Joomla 3.x UTD's structured data plugin is about to write the page's tags: Kunena's values for the forum pages
     * (threads, profiles, lists) replace its generic ones and join its graph.
     *
     * @param   string  $context  Page context (option.view)
     * @param   object  $data     opengraph, x, graph (changed in place)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function onStructuredDataBeforeRender($context, $data)
    {
        if (class_exists('KunenaSeo')) {
            KunenaSeo::render($context, $data);
        }
    }

    /**
     * Cache busting: Kunena's stylesheets and scripts (component, media, modules, plugins; site and administrator) get
     * the version in their address (?v=5.3.0), so browsers and proxies fetch them again after an update. Done once here,
     * for every asset whoever added it, before the head (and its preload links) is written.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function onBeforeCompileHead()
    {
        $doc = \Joomla\CMS\Factory::getDocument();

        if ($doc->getType() !== 'html' || !class_exists('KunenaForum')) {
            return;
        }

        $version = rawurlencode((string) KunenaForum::version());

        if ($version === '') {
            return;
        }

        foreach (array('_styleSheets', '_scripts') as $list) {
            if (empty($doc->$list) || !is_array($doc->$list)) {
                continue;
            }

            $assets = array();

            foreach ($doc->$list as $url => $attribs) {
                $assets[static::versionedUrl((string) $url, $version)] = $attribs;
            }

            $doc->$list = $assets;
        }
    }

    /**
     * @param   string  $url      Asset address
     * @param   string  $version  Kunena's version (URL-encoded)
     *
     * @return  string  The address with ?v=<version> when it is one of Kunena's files on this site
     *
     * @since 5.3.0
     */
    protected static function versionedUrl($url, $version)
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        $host = (string) parse_url($url, PHP_URL_HOST);

        if (!preg_match('#(^|/)(components/com_kunena|media/kunena|modules/mod_kunena[a-z]*|plugins/[a-z]+/kunena[a-z]*)/#', $path)
            || ($host !== '' && strcasecmp($host, (string) \Joomla\CMS\Uri\Uri::getInstance()->getHost()) !== 0)
            || preg_match('/[?&]v=/', $url)) {
            return $url;
        }

        return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . $version;
    }
}

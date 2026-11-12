<?php

/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Controllers
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Kunena Backend Config Controller
 *
 * @since  2.0
 */
#[\AllowDynamicProperties]
class KunenaAdminControllerConfig extends KunenaController
{
    /**
     * @since    2.0.0-BETA2
     * @var null|string
     */
    protected $baseurl = null;

    /**
     * @since    2.0.0-BETA2
     * @var string
     * @since    Kunena
     */
    protected $kunenabaseurl = null;

    /**
     * Construct
     *
     * @param   array $config config
     *
     * @throws Exception
     * @since    2.0.0-BETA2
     */
    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->baseurl       = 'administrator/index.php?option=com_kunena&view=config';
        $this->kunenabaseurl = 'administrator/index.php?option=com_kunena';
    }

    /**
     * Apply
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0.0-BETA2
     * @throws null
     */
    public function apply()
    {
        $this->save($this->baseurl);
    }

    /**
     * Save
     *
     * @param   null $url url
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0.0-BETA2
     * @throws null
     */
    public function save($url = null)
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
            $this->setRedirect(KunenaRoute::_($this->baseurl, false));

            return;
        }

        $properties = $this->config->getProperties();
        $defaults   = get_class_vars('KunenaConfig');
        $restored   = array();

        // Settings which may be left empty although their default isn't
        $mayBeEmpty = array('ebay_affiliate_id', 'emailheader', 'rss_limit');

        $post_config = $this->app->input->post->getArray();

        // TODO: fix depricated value
        foreach ($post_config as $postsetting => $postvalue) {
            if (Joomla\String\StringHelper::strpos($postsetting, 'cfg_') === 0) {
                // Remove cfg_ and force lower case

                if (is_array($postvalue)) {
                    $postvalue = implode(',', $postvalue);
                }

                $postname = Joomla\String\StringHelper::strtolower(Joomla\String\StringHelper::substr($postsetting, 4));


                // Never allow executable / active content extensions to be whitelisted for uploads.
                if (in_array($postname, array('filetypes', 'imagetypes', 'avatartypes'), true)) {
                    $postvalue = $this->sanitizeExtensionList($postvalue);
                }

                // A setting with a default can't be emptied (e.g. the allowed file types: none would refuse every upload):
                // it gets its default back, and the administrator is told so
                if (trim((string) $postvalue) === '' && isset($defaults[$postname]) && is_scalar($defaults[$postname])
                    && (string) $defaults[$postname] !== '' && !in_array($postname, $mayBeEmpty, true)) {
                    $postvalue             = $defaults[$postname];
                    $restored[$postname] = $postname;
                }

                // No matter what got posted, we only store config parameters defined
                // in the config class. Anything else posted gets ignored.
                if (array_key_exists($postname, $properties)) {
                    $this->config->set($postname, $postvalue);
                }
            }
        }

        $this->config->save();

        if ($restored) {
            $this->app->enqueueMessage(Text::sprintf('COM_KUNENA_CFG_EMPTY_RESTORED', implode(', ', $this->settingLabels($restored))), 'notice');
        }

        $this->app->enqueueMessage(Text::_('COM_KUNENA_CONFIGSAVED'));

        if (empty($url)) {
            $this->setRedirect(KunenaRoute::_($this->kunenabaseurl, false));

            return;
        }

        $this->setRedirect(KunenaRoute::_($url, false));
    }

    /**
     * Remove dangerous extensions (server side executable, .htaccess, svg/html) from a comma separated
     * list of allowed upload extensions.
     *
     * @param   string  $value  Comma separated list
     *
     * @return  string
     *
     * @since   5.3.1
     */
    protected function sanitizeExtensionList($value)
    {
        $forbidden = '/^(php\d*|pht|phtml|phar|phps|pl|py|cgi|asp|aspx|ascx|jsp|jspx|sh|bash|exe|dll|com|bat|cmd|vbs|js|mjs|htaccess|htpasswd|ini|user\.ini|shtml|stm|html?|xhtml|xml|svgz?|swf|hta)$/i';
        $result    = array();

        foreach (explode(',', (string) $value) as $ext) {
            $ext = strtolower(trim($ext, " \t\n\r\0\x0B."));

            if ($ext === '' || preg_match('/[^a-z0-9.]/', $ext)) {
                continue;
            }

            $isForbidden = false;

            // Check every part of multi-part extensions too (e.g. "tar.gz", "php.jpg").
            foreach (explode('.', $ext) as $part) {
                if ($part === '' || preg_match($forbidden, $part)) {
                    $isForbidden = true;
                    break;
                }
            }

            if (!$isForbidden) {
                $result[] = $ext;
            }
        }

        return implode(',', array_unique($result));
    }

    /**
     * Set default
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0.0-BETA2
     * @throws null
     */
    public function setdefault()
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
            $this->setRedirect(KunenaRoute::_($this->baseurl, false));

            return;
        }

        $this->config->reset();
        $this->config->save();

        $this->setRedirect('index.php?option=com_kunena&view=config', Text::_('COM_KUNENA_CONFIG_DEFAULT'));
    }

    /**
     * The labels of settings as the configuration page shows them (its row's first language string), for messages.
     *
     * @param   string[]  $names  Setting names
     *
     * @return  string[]
     *
     * @since 5.3.0
     */
    protected function settingLabels(array $names)
    {
        $page   = (string) @file_get_contents(KPATH_ADMIN . '/template/j3/config/default.php');
        $labels = array();

        foreach ($names as $name) {
            $label = $name;

            if (preg_match('#<tr[^>]*\\$this->config->' . preg_quote($name, '#') . '\\b.*?Text::_\\(\'([A-Z0-9_]+)\'\\)#s', $page, $m)) {
                $label = Text::_($m[1]);
            }

            $labels[] = '"' . trim(strip_tags($label)) . '"';
        }

        return $labels;
    }
}

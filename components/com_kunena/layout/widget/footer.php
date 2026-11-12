<?php

/**
 * Kunena Component
 *
 * @package         Kunena.Site
 * @subpackage      Controllers.Misc
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * KunenaLayoutWidgetFooter
 *
 * @since  K4.0
 */
#[\AllowDynamicProperties]
class KunenaLayoutWidgetFooter extends KunenaLayout
{
    /**
     * The page generation time is no longer shown in the frontend (5.3.0); kept for template overrides.
     *
     * @return null
     * @since Kunena
     */
    protected function getTime()
    {
        return null;
    }

    /**
     * Method to get the RSS URL link with image
     *
     * @return string
     * @throws Exception
     * @since Kunena
     * @throws null
     */
    protected function getRSS()
    {
        $config = KunenaFactory::getConfig();

        if ($config->enablerss) {
            $mode = $config->rss_type;

            switch ($mode) {
                case 'topic':
                    $rss_type = 'mode=topics';
                    break;
                case 'recent':
                    $rss_type = 'mode=replies';
                    break;
                case 'post':
                    $rss_type = 'layout=posts';
                    break;
            }

            $itemid = KunenaRoute::fixMissingItemID();

            if (\Joomla\CMS\Application\CMSApplication::getInstance('site')->get('sef_suffix')) {
                $url = KunenaRoute::_("index.php?option=com_kunena&view=topics&layout=default&{$rss_type}") . '?format=feed&type=rss';
            } else {
                $url = KunenaRoute::_("index.php?option=com_kunena&view=topics&format=feed&type=rss&layout=default&{$rss_type}&Itemid={$itemid}", true);
            }

            $doc = Factory::getDocument();
            $doc->addHeadLink($url, 'alternate', 'rel', array('type' => 'application/rss+xml'));

            return '<a rel="alternate" type="application/rss+xml" href="' . $url . '">' . KunenaIcons::rss($text = true) . '</a>';
        } else {
            return;
        }
    }
}

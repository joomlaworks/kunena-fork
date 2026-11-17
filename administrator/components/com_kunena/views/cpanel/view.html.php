<?php

/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Views
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * About view for Kunena cpanel
 *
 * @since  K1.X
 */
#[\AllowDynamicProperties]
class KunenaAdminViewCpanel extends KunenaView
{
    /**
     * @since Kunena
     */
    public function displayDefault()
    {
        // 5.5.0: only Options in the toolbar (kunena.org's changelog, support forum and help pages are Kunena's, not this
        // fork's); the fork's changelog is linked next to the version
        JToolbarHelper::title(Text::_('COM_KUNENA') . ': ' . Text::_('COM_KUNENA_DASHBOARD'), 'dashboard');

        if (Factory::getUser()->authorise('core.admin', 'com_kunena')) {
            JToolbarHelper::spacer();
            JToolbarHelper::preferences('com_kunena');
            JToolbarHelper::spacer();
        }

        $this->display();
    }
}

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator
 * @subpackage    Views
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Kunena > Maintenance: the maintenance jobs on one page, each with its Run button (KunenaAdminControllerMaintenance).
 *
 * @since 5.5.0 (JoomlaWorks Ltd.)
 */
#[\AllowDynamicProperties]
class KunenaAdminViewMaintenance extends KunenaView
{
    /**
     * @return  void
     *
     * @since 5.5.0
     */
    public function displayDefault()
    {
        $this->canRun      = Factory::getUser()->authorise('core.admin', 'com_kunena');
        $this->trashCounts = KunenaForumTrash::count();
        $this->namesOn     = KunenaUserNormalizer::isEnabled();
        $this->categories  = HTMLHelper::_(
            'kunenaforum.categorylist',
            'categories[]',
            0,
            null,
            array('ordering' => 'ordering', 'toplevel' => 0, 'sections' => 0, 'direction' => 1, 'unpublished' => 1, 'action' => 'admin'),
            'class="inputbox kunena-maintenance-categories" multiple="multiple" size="8" required="required"',
            'value',
            'text'
        );

        JToolbarHelper::title(Text::_('COM_KUNENA') . ': ' . Text::_('COM_KUNENA_MAINTENANCE'), 'wrench');

        $this->display();
    }
}

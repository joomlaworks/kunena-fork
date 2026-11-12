<?php

/**
 * Kunena - Baldrick AI
 *
 * @package       Kunena.Plugins
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * Super Users and Kunena administrators and moderators, to choose who gets Baldrick's notifications.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class JFormFieldBaldrickStaff extends \Joomla\CMS\Form\FormField
{
    /**
     * @var string
     * @since 5.3.0
     */
    protected $type = 'BaldrickStaff';

    /**
     * @return  string
     *
     * @since 5.3.0
     */
    protected function getInput()
    {
        $ids = array();

        // Super Users (every group with core.admin)
        $db     = Factory::getDbo();
        $groups = array();

        foreach ((array) $db->setQuery($db->getQuery(true)->select($db->quoteName('id'))->from($db->quoteName('#__usergroups')))->loadColumn() as $group) {
            if (Access::checkGroup((int) $group, 'core.admin')) {
                $groups[] = (int) $group;
            }
        }

        foreach ($groups as $group) {
            $ids = array_merge($ids, Access::getUsersByGroup($group));
        }

        // Kunena administrators and moderators
        if (class_exists('KunenaAccess') && class_exists('KunenaForum') && KunenaForum::installed()) {
            try {
                $access = KunenaAccess::getInstance();

                foreach (array_keys((array) $access->getAdmins(0, true)) as $id) {
                    $ids[] = (int) $id;
                }

                foreach (KunenaForumCategoryHelper::getCategories(false, false, 'none') as $category) {
                    $ids = array_merge($ids, array_map('intval', array_keys((array) $access->getModerators($category->id, true))));
                }

                $ids = array_merge($ids, array_map('intval', array_keys((array) $access->getModerators(0))));
            } catch (Exception $e) {
                // Kunena not ready: Super Users only
            }
        }

        $ids     = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $options = array();

        if ($ids) {
            $users = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(array('id', 'name', 'username', 'email')))
                    ->from($db->quoteName('#__users'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')')
                    ->where($db->quoteName('block') . ' = 0')
                    ->order($db->quoteName('name') . ' ASC')
            )->loadObjectList();

            foreach ((array) $users as $user) {
                $options[] = HTMLHelper::_('select.option', (int) $user->id, $user->name . ' (' . $user->username . ', ' . $user->email . ')');
            }
        }

        $value = array_map('intval', (array) $this->value);

        return HTMLHelper::_(
            'select.genericlist',
            $options,
            $this->name,
            'class="input-xxlarge" multiple="multiple" size="' . max(3, min(10, count($options))) . '"',
            'value',
            'text',
            $value,
            $this->id
        );
    }
}

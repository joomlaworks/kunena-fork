<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator.Template
 * @subpackage    Common
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

/**
 * The administrator's sections, shared by the sidebar (common/menu.php) and the dashboard's shortcuts (cpanel), so that
 * both list the same entries with the same names and icons: the forum's content first, then configuration, tools and
 * insights (a divider between the two groups).
 *
 * Each entry: view => array(language string, icon, other views that belong to it)
 *
 * @since 5.5.0
 */
return array(
    'content' => array(
        'categories'  => array('COM_KUNENA_C_FORUM', 'list-view', array('category')),
        'users'       => array('COM_KUNENA_C_USER', 'users', array('user')),
        'attachments' => array('COM_KUNENA_MENU_DESC_FILEMANAGER', 'folder-open', array('attachment')),
        'smilies'     => array('COM_KUNENA_EMOTICONS_EMOTICON_MANAGER', 'thumbs-up', array('smiley')),
        'ranks'       => array('COM_KUNENA_A_RANK_MANAGER', 'star-2', array('rank')),
        'templates'   => array('COM_KUNENA_A_TEMPLATE_MANAGER', 'color-palette', array('template')),
    ),
    'utilities' => array(
        'config'      => array('COM_KUNENA_MENU_DESC_CONFIGURATION', 'cogs', array()),
        'plugins'     => array('COM_KUNENA_PLUGIN_MANAGER', 'puzzle', array()),
        'baldrick'    => array('COM_KUNENA_BALDRICK', 'comments-2', array()),
        'logs'        => array('COM_KUNENA_LOG_MANAGER', 'search', array()),
        'statistics'  => array('COM_KUNENA_MENU_STATISTICS', 'chart', array()),
        'tools'       => array('COM_KUNENA_A_VIEW_TOOLS', 'tools', array()),
        'maintenance' => array('COM_KUNENA_MAINTENANCE', 'wrench', array()),
        'trash'       => array('COM_KUNENA_TRASH_VIEW', 'trash', array()),
    ),
);

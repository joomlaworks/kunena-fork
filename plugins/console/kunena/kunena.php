<?php

/**
 * Kunena Console Plugin
 *
 * @package       Kunena.Plugins
 * @subpackage    Console
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Gives Kunena's commands to Joomla 3.x UTD's command line (php cli/joomla.php).
 *
 * The command classes are loaded only inside the event handler, so the plugin is harmless on Joomla versions
 * without this command line (the event never fires there).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class PlgConsoleKunena extends CMSPlugin
{
    /**
     * @param   object  $app  The console application
     *
     * @return  array  The commands
     *
     * @since 5.3.0
     */
    public function onGetConsoleCommands($app = null)
    {
        if (!class_exists('Joomla\\CMS\\Console\\AbstractCommand')) {
            return array();
        }

        require_once __DIR__ . '/commands.php';

        return array(new KunenaTrashPurgeCommand(), new KunenaSampledataInstallCommand(), new KunenaSampledataRemoveCommand(), new KunenaBaldrickProcessCommand(), new KunenaBaldrickIndexCommand(), new KunenaBaldrickCheckCommand());
    }
}

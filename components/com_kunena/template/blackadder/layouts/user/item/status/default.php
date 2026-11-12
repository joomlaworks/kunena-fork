<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.User
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// Online status: a dot and its label (and the user's status text as the title)
$user   = isset($this->user) ? $this->user : KunenaUserHelper::getMyself();
$status = $user->showOnline ? $user->getStatus() : -1;
$states = array(
    0 => array('COM_KUNENA_ONLINE', 'online'),
    1 => array('COM_KUNENA_AWAY', 'away'),
    2 => array('COM_KUNENA_BUSY', 'busy'),
    3 => array('COM_KUNENA_INVISIBLE', 'offline'),
);
$state  = isset($states[$status]) ? $states[$status] : array('COM_KUNENA_OFFLINE', 'offline');
$text   = (string) $user->getStatusText();
?>
<span class="kb-status kb-status--<?php echo $state[1]; ?>"<?php echo $text !== '' ? ' title="' . $this->escape($text) . '"' : ''; ?>><?php echo Text::_($state[0]); ?></span>

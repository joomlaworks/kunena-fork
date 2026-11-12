<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Topic
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Moderators' bulk actions under a thread list: $kbActions (task options), $kbMove (show the destination list)
?>
<div class="kb-bulk">
	<span><?php echo Text::_('KUNENA_BLACKADDER_SELECTED'); ?></span>
	<?php echo HTMLHelper::_('select.genericlist', $kbActions, 'task', 'aria-label="' . Text::_('COM_KUNENA_BULK_CHOOSE_ACTION') . '"', 'value', 'text', 0, 'kchecktask'); ?>
	<?php if (!empty($kbMove)) :
	    $options = array(HTMLHelper::_('select.option', '0', Text::_('COM_KUNENA_BULK_CHOOSE_DESTINATION')));
	    echo HTMLHelper::_('kunenaforum.categorylist', 'target', 0, $options, array(), 'disabled="disabled"', 'value', 'text', 0, 'kchecktarget');
	endif; ?>
	<button class="kb-btn kb-btn--sm" type="submit" name="kcheckgo"><?php echo Text::_('COM_KUNENA_GO'); ?></button>
</div>

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

// Forum settings (Kunena's user options) and clearing the drafts this browser keeps
?>
<div class="kb-card">
	<?php foreach ($this->settings as $field) : ?>
		<div class="kb-field kb-joomla-field"><?php echo $field->label; ?><?php echo $field->field; ?></div>
	<?php endforeach; ?>
	<div class="kb-field">
		<span><?php echo Text::_('COM_KUNENA_USER_SETTINGS_CLEAR'); ?></span>
		<button type="button" class="kb-btn kb-btn--sm" data-kb-clear-storage data-kb-done="<?php echo $this->escape(Text::_('COM_KUNENA_CLEARED')); ?>"><?php echo Text::_('COM_KUNENA_USER_SETTINGS_CLEAR'); ?></button>
	</div>
</div>

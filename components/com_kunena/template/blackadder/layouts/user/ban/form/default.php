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


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Ban (or edit the ban of) a user. The Joomla-level ban and the removal of the spammer's content are preselected.
$ban        = $this->banInfo;
$expiration = (string) $ban->expiration;
$expiration = $expiration && substr($expiration, 0, 4) !== '9999' && substr($expiration, 0, 4) !== '0000' ? str_replace(' ', 'T', substr($expiration, 0, 16)) : '';
$options    = array(
    'delsignature'    => 'COM_KUNENA_MODERATE_DELETE_BAD_SIGNATURE',
    'delavatar'       => 'COM_KUNENA_MODERATE_DELETE_BAD_AVATAR',
    'delprofileinfo'  => 'COM_KUNENA_MODERATE_DELETE_BAD_PROFILEINFO',
    'bandelposts'     => 'COM_KUNENA_MODERATE_DELETE_ALL_POSTS',
    'bandelpostsperm' => 'COM_KUNENA_MODERATE_DELETE_PERM_ALL_POSTS',
);
?>
<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user'); ?>" method="post" id="kform-ban" class="kb-card kb-form">
	<input type="hidden" name="task" value="ban"/>
	<input type="hidden" name="userid" value="<?php echo (int) $this->profile->userid; ?>"/>
	<?php echo HTMLHelper::_('form.token'); ?>

	<p class="kb-muted"><?php echo $this->escape($this->profile->username); ?> &middot; <?php echo Text::_('COM_KUNENA_BAN_USERID') . ' ' . (int) $this->profile->userid; ?></p>

	<div class="kb-form-row">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_BAN_BANLEVEL'); ?>
			<select name="banlevel" id="ban-level">
				<option value="0"<?php echo $ban->exists() && !$ban->blocked ? ' selected' : ''; ?>><?php echo Text::_('COM_KUNENA_BAN_BANLEVEL_KUNENA'); ?></option>
				<option value="1"<?php echo !$ban->exists() || $ban->blocked ? ' selected' : ''; ?>><?php echo Text::_('COM_KUNENA_BAN_BANLEVEL_JOOMLA'); ?></option>
			</select>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_BAN_EXPIRETIME'); ?>
			<input type="datetime-local" name="expiration" id="ban-expiration" value="<?php echo $this->escape($expiration); ?>"/>
			<small class="kb-muted"><?php echo Text::_('COM_KUNENA_BAN_STARTEXPIRETIME_DESC'); ?></small>
		</label>
	</div>

	<label class="kb-field"><?php echo Text::_('COM_KUNENA_BAN_PUBLICREASON'); ?>
		<textarea id="ban-public" name="reason_public" rows="3"><?php echo $this->escape((string) $ban->reason_public); ?></textarea>
		<small class="kb-muted"><?php echo Text::_('COM_KUNENA_BAN_PUBLICREASON_DESC'); ?></small>
	</label>
	<label class="kb-field"><?php echo Text::_('COM_KUNENA_BAN_PRIVATEREASON'); ?>
		<textarea id="ban-private" name="reason_private" rows="3"><?php echo $this->escape((string) $ban->reason_private); ?></textarea>
		<small class="kb-muted"><?php echo Text::_('COM_KUNENA_BAN_PRIVATEREASON_DESC'); ?></small>
	</label>
	<label class="kb-field"><?php echo Text::_('COM_KUNENA_BAN_ADDCOMMENT'); ?>
		<textarea id="ban-comment" name="comment" rows="2"></textarea>
		<small class="kb-muted"><?php echo Text::_('COM_KUNENA_BAN_ADDCOMMENT_DESC'); ?></small>
	</label>

	<div class="kb-form-options kb-form-options--stack">
		<?php if ($ban->exists()) : ?>
			<label class="kb-check"><input type="checkbox" name="delban" value="delban"/> <?php echo Text::_('COM_KUNENA_MODERATE_REMOVE_BAN'); ?></label>
		<?php endif; ?>
		<?php foreach ($options as $name => $label) : ?>
			<label class="kb-check"><input type="checkbox" name="<?php echo $name; ?>" value="<?php echo $name; ?>" checked/> <?php echo Text::_($label); ?></label>
		<?php endforeach; ?>
	</div>

	<div class="kb-toolbar">
		<span></span>
		<button type="submit" class="kb-btn kb-btn--primary" name="Submit"><?php echo Text::_($ban->id ? 'COM_KUNENA_BAN_EDIT' : 'COM_KUNENA_BAN_NEW'); ?></button>
	</div>
</form>

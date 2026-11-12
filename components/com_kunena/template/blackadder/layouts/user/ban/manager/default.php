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

// Banned users (moderators' profile tab)
?>
<?php if (empty($this->userBans)) : ?>
	<p class="kb-empty"><?php echo Text::_('COM_KUNENA_BAN_NO_BANNED_USERS'); ?></p>
<?php else : ?>
	<div class="kb-table kb-simple">
		<div class="kb-table-head">
			<span><?php echo Text::_('COM_KUNENA_BAN_BANNEDUSER'); ?></span>
			<span><?php echo Text::_('COM_KUNENA_BAN_BANNEDFROM'); ?></span>
			<span><?php echo Text::_('COM_KUNENA_BAN_STARTTIME'); ?></span>
			<span><?php echo Text::_('COM_KUNENA_BAN_EXPIRETIME'); ?></span>
		</div>
		<?php foreach ($this->userBans as $banInfo) : ?>
			<div class="kb-row">
				<span><?php echo $banInfo->getUser()->getLink(); ?> <span class="kb-muted">#<?php echo (int) $banInfo->userid; ?></span></span>
				<span><?php echo Text::_($banInfo->blocked ? 'COM_KUNENA_BAN_BANLEVEL_JOOMLA' : 'COM_KUNENA_BAN_BANLEVEL_KUNENA'); ?></span>
				<span><?php echo $banInfo->getCreationDate()->toKunena('datetime'); ?></span>
				<span><?php echo $banInfo->isLifetime() ? Text::_('COM_KUNENA_BAN_LIFETIME') : $banInfo->getExpirationDate()->toKunena('datetime'); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
	<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination->setDisplayedPages(5))->set('display', true); ?>
<?php endif; ?>

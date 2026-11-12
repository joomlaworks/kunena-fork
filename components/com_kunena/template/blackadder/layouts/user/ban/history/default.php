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

// A user's bans, newest first, with the reasons and the moderators' comments
?>
<?php if (empty($this->banHistory)) : ?>
	<p class="kb-empty"><?php echo Text::sprintf('COM_KUNENA_BAN_USER_NOHISTORY', $this->escape($this->profile->getName())); ?></p>
<?php else : ?>
	<ol class="kb-bans">
		<?php foreach ($this->banHistory as $banInfo) : ?>
			<li class="kb-ban">
				<div class="kb-ban-head">
					<span class="kb-chip kb-chip--solid"><?php echo Text::_($banInfo->blocked ? 'COM_KUNENA_BAN_BANLEVEL_JOOMLA' : 'COM_KUNENA_BAN_BANLEVEL_KUNENA'); ?></span>
					<span><?php echo $banInfo->getCreationDate()->toKunena('datetime'); ?> &rarr; <?php echo $banInfo->isLifetime() ? Text::_('COM_KUNENA_BAN_LIFETIME') : $banInfo->getExpirationDate()->toKunena('datetime'); ?></span>
					<span class="kb-muted"><?php echo Text::_('COM_KUNENA_BAN_CREATEDBY') . ' ' . $banInfo->getCreator()->getLink(); ?></span>
					<?php if ($banInfo->modified_by && $banInfo->modified_time) : ?>
						<span class="kb-muted"><?php echo Text::_('COM_KUNENA_BAN_MODIFIEDBY') . ' ' . $banInfo->getModifier()->getLink() . ' ' . $banInfo->getModificationDate()->toKunena('datetime'); ?></span>
					<?php endif; ?>
				</div>
				<dl class="kb-ban-body">
					<?php if ($banInfo->reason_public) : ?>
						<dt><?php echo Text::_('COM_KUNENA_BAN_PUBLICREASON'); ?></dt>
						<dd><?php echo KunenaHtmlParser::parseText($banInfo->reason_public); ?></dd>
					<?php endif; ?>
					<?php if ($this->me->isModerator() && $banInfo->reason_private) : ?>
						<dt><?php echo Text::_('COM_KUNENA_BAN_PRIVATEREASON'); ?></dt>
						<dd><?php echo KunenaHtmlParser::parseText($banInfo->reason_private); ?></dd>
					<?php endif; ?>
					<?php if ($this->me->isModerator() && !empty($banInfo->comments)) : ?>
						<?php foreach ($banInfo->comments as $comment) : ?>
							<dt><?php echo Text::sprintf('COM_KUNENA_BAN_COMMENT_BY', KunenaFactory::getUser((int) $comment->userid)->getLink()) . ', ' . KunenaDate::getInstance($comment->time)->toKunena(); ?></dt>
							<dd><?php echo KunenaHtmlParser::parseText($comment->comment); ?></dd>
						<?php endforeach; ?>
					<?php endif; ?>
				</dl>
			</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Announcement
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// One announcement in the list
$announcement = $this->announcement;
$author       = $announcement->getAuthor();
?>
<div class="kb-row kb-feed-row">
	<div class="kb-feed-avatar"><?php echo KunenaFactory::getTemplate()->avatar($author, 'md'); ?></div>
	<div class="kb-feed-main">
		<div class="kb-topic-title">
			<?php echo HTMLHelper::_('kunenaforum.link', $announcement->getUri(), $announcement->displayField('title'), null, null, ''); ?>
			<?php if (!$announcement->published) : ?>
				<span class="kb-chip"><?php echo Text::_('KUNENA_BLACKADDER_UNPUBLISHED'); ?></span>
			<?php endif; ?>
		</div>
		<div class="kb-topic-meta">
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_BY_X', $this->escape($author->getName()), $announcement->displayField('created', 'date_today')); ?></span>
			<?php if ($this->checkbox && $this->canEdit()) : ?>
				<a class="kb-action" href="<?php echo $announcement->getUrl('edit'); ?>"><?php echo Text::_('COM_KUNENA_ANN_EDIT'); ?></a>
			<?php endif; ?>
		</div>
	</div>
	<?php if ($this->checkbox) : ?>
		<div><input class="kb-check-topic" type="checkbox" name="cid[]" value="<?php echo (int) $announcement->id; ?>" aria-label="<?php echo $this->escape($announcement->title); ?>"/></div>
	<?php endif; ?>
</div>

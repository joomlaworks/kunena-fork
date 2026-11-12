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

// A user's attachments (profile tab): cards with the file, its size and thread; the owner and moderators can delete
$template  = KunenaFactory::getTemplate();
$canManage = $this->me->userid == $this->profile->userid || $this->me->isModerator();
$visible   = array();

foreach ($this->attachments ?: array() as $attachment) {
    if ($attachment->isAuthorised('read', $this->me)) {
        $visible[] = $attachment;
    }
}
?>
<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user'); ?>" method="post" data-kb-bulk>
	<input type="hidden" name="task" value="delfile"/>
	<?php echo HTMLHelper::_('form.token'); ?>

	<?php if (!$visible) : ?>
		<p class="kb-empty"><?php echo Text::_('COM_KUNENA_USER_NO_ATTACHMENTS'); ?></p>
	<?php else : ?>
		<ul class="kb-files-grid">
			<?php foreach ($visible as $attachment) :
			    $message = $attachment->getMessage();
			    ?>
				<li class="kb-file-card">
					<?php echo $attachment->getLayout()->render('thumbnail'); ?>
					<div class="kb-file-card-meta">
						<?php echo $this->getTopicLink($message->getTopic(), $message, null, null, 'kb-latest-title', null, false, true); ?>
					</div>
					<?php if ($canManage && $attachment->isAuthorised('delete')) : ?>
						<label class="kb-check"><input class="kb-check-topic" type="checkbox" name="cid[]" value="<?php echo (int) $attachment->id; ?>"/> <?php echo Text::_('COM_KUNENA_DELETE'); ?></label>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ($canManage) : ?>
			<div class="kb-toolbar">
				<span></span>
				<button type="submit" class="kb-btn kb-btn--sm" data-kb-confirm="<?php echo $this->escape(Text::_('COM_KUNENA_FILES_DELETE_SELECTED_MODAL_DESCRIPTION')); ?>"><?php echo $template->icon('trash') . ' ' . Text::_('COM_KUNENA_FILES_DELETE'); ?></button>
			</div>
		<?php endif; ?>

		<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination->setDisplayedPages(5))->set('display', true); ?>
	<?php endif; ?>
</form>

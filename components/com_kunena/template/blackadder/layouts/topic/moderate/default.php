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

// Moderate a thread or a post: move it to another category, merge it into another thread, split replies off
$template = KunenaFactory::getTemplate();
$isPost   = isset($this->message);
$subject  = $isPost ? $this->message->subject : $this->topic->subject;
$back     = $isPost ? $this->message->getUrl($this->category, false) : $this->topic->getUrl($this->category, false);
?>
<div class="kb-title">
	<h1><?php echo Text::_($isPost ? 'COM_KUNENA_TITLE_MODERATE_MESSAGE' : 'COM_KUNENA_TITLE_MODERATE_TOPIC'); ?></h1>
	<div class="kb-meta">
		<?php echo $this->getCategoryLink($this->category, $this->escape($this->category->name), '', 'kb-chip kb-chip--cat', true, false); ?>
		<span><?php echo $this->topic->displayField('subject'); ?></span>
		<?php if (isset($this->userLink)) : ?><span><?php echo $this->userLink; ?></span><?php endif; ?>
	</div>
</div>

<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic'); ?>" method="post" id="myform" class="kb-form" data-kb-moderate
      data-kb-topics-url="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=category&format=raw', false); ?>">
	<input type="hidden" name="task" value="move"/>
	<input type="hidden" name="catid" value="<?php echo (int) $this->category->id; ?>"/>
	<input type="hidden" name="id" value="<?php echo (int) $this->topic->id; ?>"/>
	<?php if ($isPost) : ?>
		<input type="hidden" name="mesid" value="<?php echo (int) $this->message->id; ?>"/>
	<?php endif; ?>
	<?php echo HTMLHelper::_('form.token'); ?>

	<?php if ($isPost) : ?>
		<div role="article" class="kb-post kb-card">
			<div class="kb-post-avatar"><?php echo $template->avatar($this->message->getAuthor(), 'lg', $this->message->name); ?></div>
			<div class="kb-post-head">
				<span class="kb-post-author"><?php echo $this->message->getAuthor()->getLink(); ?></span>
				<span class="kb-post-when"><?php echo $this->message->getTime()->toSpan('config_post_dateformat', 'config_post_dateformat_hover'); ?></span>
			</div>
			<div class="kb-post-body"><div class="kmsg"><?php echo $this->message->displayField('message'); ?></div></div>
		</div>
	<?php endif; ?>

	<div class="kb-card">
		<h2 class="kb-subhead"><?php echo Text::_('COM_KUNENA_MODERATION_DEST'); ?></h2>
		<div class="kb-form-row">
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_MODERATION_DEST_CATEGORY'); ?>
				<?php echo $this->getCategoryList(); ?>
			</label>
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_MODERATION_DEST_TOPIC'); ?>
				<?php echo HTMLHelper::_('select.genericlist', $this->getTopicOptions(), 'targettopic', '', 'value', 'text', 0, 'kmod_topics'); ?>
			</label>
		</div>
		<label class="kb-field" id="kmod_targetid" data-kb-target-id><?php echo Text::_('COM_KUNENA_MODERATION_TARGET_TOPIC_ID'); ?>
			<input type="number" min="1" name="targetid" value=""/>
		</label>
		<label class="kb-field" id="kmod_subject" data-kb-subject><?php echo Text::_('COM_KUNENA_MODERATION_TITLE_DEST_SUBJECT'); ?>
			<input type="text" name="subject" value="<?php echo $this->escape((string) $subject); ?>" maxlength="<?php echo (int) $template->params->get('SubjectLengthMessage', 100); ?>"/>
		</label>
		<div class="kb-form-options kb-form-options--stack">
			<?php if (!empty($this->replies)) : ?>
				<label class="kb-check"><input type="radio" name="mode" value="selected" checked/> <?php echo Text::_('COM_KUNENA_MODERATION_MOVE_SELECTED'); ?></label>
				<label class="kb-check"><input type="radio" name="mode" value="newer"/> <?php echo Text::sprintf('COM_KUNENA_MODERATION_MOVE_NEWER', $this->escape($this->replies)); ?></label>
			<?php endif; ?>
			<label class="kb-check"><input type="checkbox" name="changesubject" value="1"/> <?php echo Text::_('COM_KUNENA_MODERATION_CHANGE_SUBJECT_ON_REPLIES'); ?></label>
			<?php if ($isPost && $this->topic->poll_id && $this->topic->first_post_id != $this->message->id) : ?>
				<label class="kb-check"><input type="checkbox" name="keep_poll" value="1"/> <?php echo Text::_('COM_KUNENA_MODERATION_TOPIC_KEEP_POLL'); ?></label>
			<?php endif; ?>
			<?php if (!$isPost) : ?>
				<label class="kb-check"><input type="checkbox" name="shadow" value="1"<?php echo $this->config->boxghostmessage ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_MODERATION_TOPIC_SHADOW'); ?></label>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($isPost && $this->message->getAuthor()->id) : ?>
		<details class="kb-card kb-search-options">
			<summary class="kb-action"><?php echo Text::_('COM_KUNENA_TITLE_MODERATE_TAB_BAN_HISTORY'); ?></summary>
			<?php echo $this->subLayout('User/Ban/History')->set('profile', $this->message->getAuthor())->set('banHistory', $this->banHistory)->set('me', $this->me); ?>
		</details>
	<?php endif; ?>

	<div class="kb-toolbar">
		<a class="kb-btn kb-btn--ghost" href="<?php echo $this->escape($back); ?>"><?php echo Text::_('COM_KUNENA_BACK'); ?></a>
		<button type="submit" class="kb-btn kb-btn--primary"><?php echo Text::_('COM_KUNENA_POST_MODERATION_PROCEED'); ?></button>
	</div>
</form>

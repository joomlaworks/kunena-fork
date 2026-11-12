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

// Poll voting card: the options as radio buttons, Vote, and the results in a collapsible section
$poll = $this->poll;
$span = '';

if (!empty($poll->polltimetolive) && substr((string) $poll->polltimetolive, 0, 4) > '1901') {
    $span = Text::sprintf(!empty($this->polllifespan) ? 'COM_KUNENA_POLL_WAS_ENDED' : 'COM_KUNENA_POLL_RUNS_UNTILL', $poll->polltimetolive);
}
?>
<div class="kb-poll">
	<h2><?php echo KunenaFactory::getTemplate()->icon('bar-chart') . ' ' . KunenaHtmlParser::parseText($poll->title); ?></h2>
	<?php if ($span) : ?><p class="kb-muted"><?php echo $span; ?></p><?php endif; ?>

	<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic'); ?>" method="post" class="kb-poll-vote">
		<input type="hidden" name="task" value="vote"/>
		<input type="hidden" name="catid" value="<?php echo (int) $this->topic->category_id; ?>"/>
		<input type="hidden" name="id" value="<?php echo (int) $this->topic->id; ?>"/>
		<?php echo HTMLHelper::_('form.token'); ?>
		<?php foreach ($poll->getOptions() as $option) : ?>
			<label class="kb-check">
				<input type="radio" name="kpollradio" value="<?php echo (int) $option->id; ?>" required<?php echo $this->userhasvoted && $this->userhasvoted->lastvote == $option->id ? ' checked' : ''; ?>/>
				<?php echo KunenaHtmlParser::parseText($option->text); ?>
			</label>
		<?php endforeach; ?>
		<div class="kb-toolbar-group">
			<button type="submit" class="kb-btn kb-btn--primary kb-btn--sm"><?php echo Text::_($this->userhasvoted && !$this->config->pollallowvoteone ? 'COM_KUNENA_POLL_BUTTON_CHANGEVOTE' : 'COM_KUNENA_POLL_BUTTON_VOTE'); ?></button>
		</div>
	</form>

	<details class="kb-poll-more">
		<summary class="kb-action"><?php echo Text::_('COM_KUNENA_POLL_BUTTON_VIEW_RESULTS'); ?></summary>
		<?php echo $this->subLayout('Topic/Poll/Results')->set('poll', $poll)->set('usercount', $this->usercount)->set('me', $this->me)->set('topic', $this->topic)->set('category', $this->category)->set('show_title', false); ?>
	</details>
</div>

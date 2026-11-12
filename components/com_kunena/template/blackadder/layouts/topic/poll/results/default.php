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


use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

// Poll results: one bar per option. Shown on its own (show_title) or inside the voting card ("View results").
$poll  = $this->poll;
$total = max((int) $poll->getTotal(), 1);
$span  = '';

if (!empty($poll->polltimetolive) && substr((string) $poll->polltimetolive, 0, 4) > '1901') {
    $span = Text::sprintf(!empty($this->polllifespan) ? 'COM_KUNENA_POLL_WAS_ENDED' : 'COM_KUNENA_POLL_RUNS_UNTILL', $poll->polltimetolive);
}

$results = function () use ($poll, $total) {
    $html = '<ul class="kb-poll-results">';

    foreach ($poll->getOptions() as $option) {
        $percentage = round(((int) $option->votes * 100) / $total, 1);
        $html .= '<li><div class="kb-poll-line"><span>' . KunenaHtmlParser::parseText($option->text) . '</span>'
            . '<span class="kb-muted kb-num">' . ((int) $option->votes > 0 ? (int) $option->votes : Text::_('COM_KUNENA_POLL_NO_VOTE')) . ' &middot; ' . $percentage . '%</span></div>'
            . '<div class="kb-poll-bar"><span style="width:' . $percentage . '%"></span></div></li>';
    }

    return $html . '</ul>';
};
?>
<?php if (!empty($this->show_title)) : ?>
<div class="kb-poll">
	<h2><?php echo KunenaFactory::getTemplate()->icon('bar-chart') . ' ' . KunenaHtmlParser::parseText($poll->title); ?></h2>
	<?php if ($span) : ?><p class="kb-muted"><?php echo $span; ?></p><?php endif; ?>
	<?php echo $results(); ?>
<?php else : ?>
	<?php echo $results(); ?>
<?php endif; ?>
	<p class="kb-poll-foot">
		<span class="kb-muted"><?php echo Text::_('COM_KUNENA_POLL_VOTERS_TOTAL') . ' <b>' . (int) $this->usercount . '</b>'; ?>
		<?php if (!empty($this->users_voted_list)) : ?>
			(<?php echo implode(', ', $this->users_voted_list); ?><?php echo !empty($this->users_voted_morelist) ? ', ' . implode(', ', $this->users_voted_morelist) : ''; ?>)
		<?php endif; ?>
		</span>
		<?php if (!$this->me->exists()) : ?>
			<span class="kb-muted"><?php echo Text::_('COM_KUNENA_POLL_NOT_LOGGED'); ?></span>
		<?php elseif (!empty($this->show_title) && $this->topic->isAuthorised('poll.vote') && $this->topic->isAuthorised('reply')) : ?>
			<a class="kb-action" href="<?php echo KunenaRoute::_("index.php?option=com_kunena&view=topic&layout=vote&catid={$this->category->id}&id={$this->topic->id}"); ?>"><?php echo Text::_('COM_KUNENA_POLL_BUTTON_CHANGE_VOTE'); ?></a>
		<?php endif; ?>
		<?php if ($this->me->isModerator($this->category)) : ?>
			<a class="kb-action kb-action--danger" rel="nofollow" data-kb-confirm="<?php echo $this->escape(Text::_('COM_KUNENA_TOPIC_MODAL_DESC_VOTE_RESET')); ?>"
			   href="<?php echo KunenaRoute::_("index.php?option=com_kunena&view=topic&catid={$this->category->id}&id={$this->topic->id}&pollid={$poll->id}&task=resetvotes&" . Session::getFormToken() . '=1'); ?>"><?php echo Text::_('COM_KUNENA_TOPIC_VOTE_RESET'); ?></a>
		<?php endif; ?>
	</p>
<?php if (!empty($this->show_title)) : ?>
</div>
<?php endif; ?>

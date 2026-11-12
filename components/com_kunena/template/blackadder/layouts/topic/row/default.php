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

/*
 * One thread in a list (Discourse-like): no icon on the left; title with state flags, category and starter below;
 * posters (starter and last poster), replies, views and the last activity on the right.
 * category.php hides the category (we are inside it); user.php is the same as this one.
 */
$topic      = $this->topic;
$category   = $topic->getCategory();
$template   = KunenaFactory::getTemplate();
$config     = KunenaConfig::getInstance();
$userTopic  = $topic->getUserTopic();
$topicPages = $topic->getPagination(null, $config->messages_per_page, 3);
$starter    = $topic->getFirstPostAuthor();
$last       = $topic->getLastPostAuthor();
$replies    = (int) $topic->getReplies();
$showCat    = empty($kbHideCategory);
$classes    = 'kb-row kb-cat--' . preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $category->alias));

if ($topic->unread) {
    $classes .= ' kb-row--unread';
}

if ($topic->ordering) {
    $classes .= ' kb-row--sticky';
}

if ($topic->hold) {
    $classes .= $topic->hold == 1 ? ' kb-row--unapproved' : ' kb-row--deleted';
}

$posterLink = function ($user, $guestName) use ($template, $category) {
    $avatar = $template->avatar($user, 'sm', $guestName);

    return $user->exists() ? $user->getLink($avatar, null, 'nofollow', '', '', $category->id) : $avatar;
};

if (!empty($this->spacing)) : ?>
	<div class="kb-row kb-row--spacer" aria-hidden="true"></div>
<?php endif; ?>

<div class="<?php echo $classes; ?>">
	<div class="kb-topic-main">
		<div class="kb-topic-title">
			<?php if ($topic->unread) : ?>
				<span class="kb-dot" title="<?php echo Text::_('KUNENA_BLACKADDER_NEW'); ?>"></span>
			<?php endif; ?>
			<?php if ($topic->ordering) : ?>
				<?php echo $template->icon('pin-angle-fill', Text::_('COM_KUNENA_BUTTON_TOPIC_STICKY')); ?>
			<?php endif; ?>
			<?php if ($topic->locked) : ?>
				<?php echo $template->icon('lock-fill', Text::_('COM_KUNENA_LOCKED')); ?>
			<?php endif; ?>
			<?php if ($template->params->get('labels')) : ?>
				<?php echo $this->subLayout('Widget/Label')->set('topic', $topic)->setLayout('default'); ?>
			<?php endif; ?>
			<?php
            if ($topic->unread) {
                echo $this->getTopicLink($topic, 'unread', $this->escape($topic->subject), null, 'topictitle', $category, true, true);
                echo ' <span class="kb-new">' . Text::sprintf('KUNENA_BLACKADDER_UNREAD', (int) $topic->unread) . '</span>';
            } else {
                echo $this->getTopicLink($topic, null, null, null, 'topictitle', $category, true, false);
            }
?>
			<span class="kb-topic-flags">
				<?php if ($userTopic->favorite) : ?><?php echo $template->icon('star-fill', Text::_('COM_KUNENA_FAVORITE')); ?><?php endif; ?>
				<?php if ($topic->attachments) : ?><?php echo $template->icon('paperclip', Text::_('COM_KUNENA_ATTACH')); ?><?php endif; ?>
				<?php if ($topic->poll_id && $category->allow_polls) : ?><?php echo $template->icon('bar-chart', Text::_('COM_KUNENA_ADMIN_POLLS')); ?><?php endif; ?>
			</span>
		</div>
		<div class="kb-topic-meta">
			<?php if ($showCat) : ?>
				<?php echo $this->getCategoryLink($category, $this->escape($category->name), '', 'kb-chip kb-chip--cat', true, false); ?>
			<?php endif; ?>
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_STARTED_BY_ON', $starter->getLink(null, null, 'nofollow', '', '', $category->id), $topic->getFirstPostTime()->toSpan('config_post_dateformat', 'config_post_dateformat_hover')); ?></span>
			<?php if ($topicPages->pagesTotal > 1) : ?>
				<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $topicPages)->setLayout('simple'); ?>
			<?php endif; ?>
			<span class="kb-mobile-stats">
				<?php echo Text::sprintf('KUNENA_BLACKADDER_X_REPLIES', $this->formatLargeNumber($replies)); ?> ·
				<?php echo $this->getTopicLink($topic, 'last', $topic->getLastPostTime()->toKunena('ago'), null, null, $category, false, true); ?>
			</span>
		</div>
	</div>

	<div class="kb-posters">
		<?php echo $posterLink($starter, $topic->first_post_guest_name); ?>
		<?php if ($replies && ($last->userid != $starter->userid || !$last->userid)) : ?>
			<?php echo $posterLink($last, $topic->last_post_guest_name); ?>
		<?php endif; ?>
	</div>

	<div class="kb-stat<?php echo $replies >= 20 ? ' kb-stat--hot' : ''; ?>" title="<?php echo Text::_('KUNENA_BLACKADDER_REPLIES'); ?>"><?php echo $this->formatLargeNumber($replies); ?></div>
	<div class="kb-stat kb-muted" title="<?php echo Text::_('KUNENA_BLACKADDER_VIEWS'); ?>"><?php echo $this->formatLargeNumber((int) $topic->hits); ?></div>

	<div class="kb-activity">
		<?php echo $this->getTopicLink($topic, 'last', $topic->getLastPostTime()->toKunena('ago'), Text::_('COM_KUNENA_GEN_LAST_POST'), null, $category, false, true); ?>
		<span><?php echo $this->escape($last->getName($topic->last_post_guest_name)); ?></span>
	</div>

	<?php if (!empty($this->checkbox)) : ?>
		<div>
			<input class="kb-check-topic" type="checkbox" name="topics[<?php echo (int) $topic->id; ?>]" value="1"
			       aria-label="<?php echo $this->escape($topic->subject); ?>"/>
		</div>
	<?php endif; ?>
</div>

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Message
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// One post in a list: the thread it belongs to, the category, an excerpt of the post, its author and time
$message  = $this->message;
$topic    = $message->getTopic();
$category = $message->getCategory();
$author   = $message->getAuthor();
$template = KunenaFactory::getTemplate();
$config   = KunenaConfig::getInstance();
$me       = KunenaUserHelper::getMyself();
$isReply  = $message->id != $topic->first_post_id;
$excerpt  = !$me->userid && $isReply && $config->teaser ? '' : KunenaHtmlParser::stripBBCode($message->message, 240);
$avatar   = $template->avatar($author, 'md', $message->name);
?>
<div class="kb-row kb-feed-row kb-cat--<?php echo preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $category->alias)); ?><?php echo $topic->unread ? ' kb-row--unread' : ''; ?>">
	<div class="kb-feed-avatar"><?php echo $author->exists() ? $author->getLink($avatar, null, 'nofollow', '', '', $category->id) : $avatar; ?></div>
	<div class="kb-feed-main">
		<div class="kb-topic-title">
			<?php if ($topic->unread) : ?>
				<span class="kb-dot" title="<?php echo Text::_('KUNENA_BLACKADDER_NEW'); ?>"></span>
			<?php endif; ?>
			<?php if ($topic->locked) : ?>
				<?php echo $template->icon('lock-fill', Text::_('COM_KUNENA_LOCKED_TOPIC')); ?>
			<?php endif; ?>
			<?php echo $this->getTopicLink($topic, $message, $this->escape($isReply ? $message->subject : $topic->subject), null, 'topictitle', $category, true, false); ?>
		</div>
		<?php if ($excerpt) : ?>
			<p class="kb-feed-excerpt"><?php echo $excerpt; ?></p>
		<?php endif; ?>
		<div class="kb-topic-meta">
			<?php echo $this->getCategoryLink($category, $this->escape($category->name), '', 'kb-chip kb-chip--cat', true, false); ?>
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_BY_X', $author->getLink(null, null, 'nofollow', '', '', $category->id), $message->getTime()->toSpan('config_post_dateformat', 'config_post_dateformat_hover')); ?></span>
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_X_REPLIES', $this->formatLargeNumber((int) $topic->getReplies())); ?></span>
		</div>
	</div>
	<?php if (!empty($this->checkbox)) : ?>
		<div><input class="kb-check-topic" type="checkbox" name="posts[<?php echo (int) $message->id; ?>]" value="1" aria-label="<?php echo $this->escape($message->subject); ?>"/></div>
	<?php endif; ?>
</div>

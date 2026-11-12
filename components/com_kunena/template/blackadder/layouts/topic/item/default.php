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
 * A thread: title and meta, the actions (reply, subscribe, favourite, moderation menu), the poll, the posts, one reply
 * box at the bottom, pagination at the bottom (centred). No timeline.
 */
$topic    = $this->topic;
$category = $this->category;
$template = KunenaFactory::getTemplate();
$config   = KunenaConfig::getInstance();
$quick    = (int) $template->params->get('quick', 2);
$count    = 1;
$classes  = 'kb-topic kb-cat--' . preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $category->alias));

if ($topic->ordering) {
    $classes .= ' kb-topic--sticky';
}

if ($topic->locked) {
    $classes .= ' kb-topic--locked';
}

// Mentions and emojis in the reply box (Kunena's jQuery plugins; their styles are in blackadder.css)
$this->addScript('jquery.caret.js');
$this->addScript('jquery.atwho.js');
?>
<div class="<?php echo $classes; ?> kb-stack">
	<?php if ($category->headerdesc) : ?>
		<div class="kb-alert"><?php echo $category->displayField('headerdesc'); ?></div>
	<?php endif; ?>

	<div class="kb-title">
		<h1>
			<?php if ($template->params->get('labels')) : ?>
				<?php echo $this->subLayout('Widget/Label')->set('topic', $topic)->setLayout('default'); ?>
			<?php endif; ?>
			<?php echo $topic->displayField('subject'); ?>
		</h1>
		<div class="kb-meta">
			<?php echo $this->getCategoryLink($category, $this->escape($category->name), '', 'kb-chip kb-chip--cat', true, false); ?>
			<?php if ($topic->locked) : ?>
				<span class="kb-chip"><?php echo $template->icon('lock-fill') . ' ' . Text::_('COM_KUNENA_LOCKED'); ?></span>
			<?php endif; ?>
			<?php if ($topic->ordering) : ?>
				<span class="kb-chip"><?php echo $template->icon('pin-angle-fill') . ' ' . Text::_('COM_KUNENA_BUTTON_TOPIC_STICKY'); ?></span>
			<?php endif; ?>
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_X_REPLIES', $this->formatLargeNumber((int) $topic->getReplies())); ?></span>
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_X_VIEWS', $this->formatLargeNumber((int) $topic->hits)); ?></span>
		</div>
	</div>

	<?php echo $this->subRequest('Topic/Item/Actions')->set('id', $topic->id); ?>

	<?php
    if ($template->params->get('displayModule')) {
        echo $this->subLayout('Widget/Module')->set('position', 'kunena_topictitle');
    }

echo $this->subRequest('Topic/Poll')->set('id', $topic->id);

if ($template->params->get('displayModule')) {
    echo $this->subLayout('Widget/Module')->set('position', 'kunena_poll');
}
?>

	<div class="kb-posts">
		<?php foreach ($this->messages as $id => $message) {
		    echo $this->subRequest('Topic/Item/Message')
		        ->set('mesid', $message->id)
		        ->set('location', $id);

		    if ($template->params->get('displayModule')) {
		        echo $this->subLayout('Widget/Module')->set('position', 'kunena_msg_row_' . $count++);
		    }
		} ?>
	</div>

	<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination)->set('display', true); ?>

	<?php if ($quick && $config->quickreply && !$config->read_only) {
	    echo $this->subLayout('Message/Edit')->set('message', $this->message)->set('topic', $topic)->set('category', $category)->setLayout('full');
	} ?>

	<?php if ($template->params->get('writeaccess')) : ?>
		<div class="kb-alert"><?php echo $this->subLayout('Widget/Writeaccess')->set('id', $topic->id); ?></div>
	<?php endif; ?>
</div>

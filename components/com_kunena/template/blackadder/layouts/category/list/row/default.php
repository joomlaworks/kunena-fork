<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Category
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// A subscribed category: name and number of topics, its latest post
$category = $this->category;
$topic    = $category->getLastTopic();
$template = KunenaFactory::getTemplate();
?>
<div class="kb-row kb-cat--<?php echo preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $category->alias)); ?>">
	<div class="kb-cat-main">
		<div class="kb-cat-name"><?php echo $this->getCategoryLink($category, $this->escape($category->name), '', null, true, false); ?></div>
		<span class="kb-muted"><?php echo Text::sprintf('COM_KUNENA_X_TOPICS_MORE', $this->formatLargeNumber($category->getTopics())); ?></span>
	</div>
	<?php if ($topic->exists()) :
	    $author = $topic->getLastPostAuthor();
	    ?>
		<div class="kb-latest">
			<?php echo $template->avatar($author, 'sm', $topic->last_post_guest_name); ?>
			<div>
				<?php echo $this->getTopicLink($topic, 'last', $this->escape($topic->subject), null, 'kb-latest-title', $category, true, true); ?>
				<span class="kb-latest-meta"><?php echo Text::sprintf('KUNENA_BLACKADDER_BY_X', $this->escape($author->getName($topic->last_post_guest_name)), $topic->getLastPostTime()->toKunena('config_post_dateformat')); ?></span>
			</div>
		</div>
	<?php else : ?>
		<div class="kb-latest kb-muted"><?php echo Text::_('COM_KUNENA_NO_POSTS'); ?></div>
	<?php endif; ?>
	<?php if (!empty($this->checkbox)) : ?>
		<div><input class="kb-check-topic" type="checkbox" name="categories[<?php echo (int) $category->id; ?>]" value="1" aria-label="<?php echo $this->escape($category->name); ?>"/></div>
	<?php endif; ?>
</div>

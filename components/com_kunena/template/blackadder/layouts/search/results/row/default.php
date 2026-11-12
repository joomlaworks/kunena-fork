<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Search
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// A search result: the post with its thread and category
$message  = $this->message;
$topic    = $message->getTopic();
$category = $topic->getCategory();
$author   = $message->getAuthor();
$template = KunenaFactory::getTemplate();
$config   = KunenaFactory::getConfig();
$me       = isset($this->me) ? $this->me : KunenaUserHelper::getMyself();
$isReply  = $message->id != $topic->first_post_id;
$avatar   = $template->avatar($author, 'lg', $message->name);
?>
<div role="article" class="kb-post kb-cat--<?php echo preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $category->alias)); ?>">
	<div class="kb-post-avatar"><?php echo $author->exists() ? $author->getLink($avatar, null, 'nofollow', '', '', $category->id) : $avatar; ?></div>
	<div class="kb-post-head">
		<span class="kb-post-author"><?php echo $author->getLink(null, null, 'nofollow', '', null, $category->id); ?></span>
		<?php echo $this->getCategoryLink($category, $this->escape($category->name), '', 'kb-chip kb-chip--cat', true, false); ?>
		<span class="kb-post-when"><?php echo $message->getTime()->toSpan('config_post_dateformat', 'config_post_dateformat_hover'); ?></span>
	</div>
	<div class="kb-post-body">
		<h3 class="kb-result-title"><?php echo $this->getTopicLink($topic, $message, $this->escape($message->subject), null, 'topictitle', $category, true, false); ?></h3>
		<div class="kmsg"><?php echo $isReply && !$me->userid && $config->teaser ? \Joomla\CMS\Language\Text::_('COM_KUNENA_TEASER_TEXT') : $message->displayField('message'); ?></div>
	</div>
</div>

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

// The thread's latest posts under the posting form, collapsed
$template = KunenaFactory::getTemplate();
$catid    = $this->topic->getCategory()->id;
?>
<details class="kb-history">
	<summary class="kb-action"><?php echo $template->icon('clock') . ' ' . Text::_('COM_KUNENA_POST_TOPIC_HISTORY') . ' ' . $this->escape($this->topic->subject); ?></summary>
	<div class="kb-posts">
		<?php foreach ($this->history as $item) :
		    $author = $item->getAuthor();
		    $avatar = $template->avatar($author, 'lg', $item->name);
		    ?>
			<div role="article" class="kb-post">
				<div class="kb-post-avatar"><?php echo $avatar; ?></div>
				<div class="kb-post-head">
					<span class="kb-post-author"><?php echo $author->getLink(null, null, 'nofollow', '', null, $catid); ?></span>
					<span class="kb-post-when"><?php echo $item->getTime()->toSpan('config_post_dateformat', 'config_post_dateformat_hover'); ?></span>
				</div>
				<div class="kb-post-body"><?php echo KunenaHtmlParser::parseBBCode($item->message, $this); ?></div>
			</div>
		<?php endforeach; ?>
	</div>
</details>

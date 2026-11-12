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

// Report a post (or a thread) to the moderators
$back = $this->message ? $this->message->getUrl($this->category, false) : $this->topic->getUrl($this->category, false);
?>
<div class="kb-title">
	<h1><?php echo Text::_('COM_KUNENA_REPORT_TO_MODERATOR'); ?></h1>
	<p><?php echo $this->escape($this->message ? $this->message->subject : $this->topic->subject); ?></p>
</div>

<form method="post" action="<?php echo KunenaRoute::_('index.php?option=com_kunena'); ?>" class="kb-form">
	<input type="hidden" name="view" value="topic"/>
	<input type="hidden" name="task" value="report"/>
	<input type="hidden" name="catid" value="<?php echo (int) $this->category->id; ?>"/>
	<input type="hidden" name="id" value="<?php echo (int) $this->topic->id; ?>"/>
	<?php if ($this->message) : ?>
		<input type="hidden" name="mesid" value="<?php echo (int) $this->message->id; ?>"/>
	<?php endif; ?>
	<?php echo HTMLHelper::_('form.token'); ?>

	<div class="kb-card">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_REPORT_REASON'); ?>
			<input type="text" name="reason" required maxlength="200"/>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_REPORT_MESSAGE'); ?>
			<textarea name="text" rows="6"></textarea>
		</label>
	</div>

	<div class="kb-toolbar">
		<a class="kb-btn kb-btn--ghost" href="<?php echo $this->escape($back); ?>"><?php echo Text::_('COM_KUNENA_CANCEL'); ?></a>
		<button type="submit" class="kb-btn kb-btn--primary" name="Submit"><?php echo Text::_('COM_KUNENA_REPORT_SEND'); ?></button>
	</div>
</form>

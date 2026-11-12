<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Announcement
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Add or edit an announcement (dates as native date/time fields; Kunena stores them in SQL format)
$a     = $this->announcement;
$local = function ($value) {
    $value = (string) $value;

    return preg_match('/^(\d{4})-\d{2}-\d{2}[ T]\d{2}:\d{2}/', $value, $m) && $m[1] > 1901 && $m[1] < 9999 ? str_replace(' ', 'T', substr($value, 0, 16)) : '';
};
$cancel = $a->exists() ? $a->getUrl('default', false) : KunenaForumAnnouncementHelper::getUrl('list', false);
?>
<div class="kb-title"><h1><?php echo Text::_($a->exists() ? 'COM_KUNENA_ANN_EDIT' : 'COM_KUNENA_ANN_ADD'); ?></h1></div>

<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=announcement'); ?>" method="post" id="editform" class="kb-form">
	<input type="hidden" name="task" value="save"/>
	<input type="hidden" name="id" value="<?php echo (int) $a->id; ?>"/>
	<?php echo HTMLHelper::_('form.token'); ?>

	<div class="kb-card">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_ANN_TITLE'); ?>
			<input type="text" name="title" required value="<?php echo $this->escape((string) $a->title); ?>" placeholder="<?php echo Text::_('COM_KUNENA_ANN_LABEL_PLACEHOLDER_TITLE'); ?>"/>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_ANN_SORTTEXT'); ?>
			<textarea name="sdescription" rows="5" required placeholder="<?php echo Text::_('COM_KUNENA_ANN_LABEL_PLACEHOLDER_SDESCRIPTION'); ?>"><?php echo $this->escape((string) $a->sdescription); ?></textarea>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_ANN_LONGTEXT'); ?>
			<textarea name="description" rows="9" placeholder="<?php echo Text::_('COM_KUNENA_ANN_LABEL_PLACEHOLDER_DESCRITPION'); ?>"><?php echo $this->escape((string) $a->description); ?></textarea>
		</label>
		<div class="kb-search-grid">
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_ANN_DATE'); ?>
				<input type="datetime-local" name="created" value="<?php echo $local($a->created); ?>"/>
			</label>
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_ANN_DATE_UP'); ?>
				<input type="datetime-local" name="publish_up" value="<?php echo $local($a->publish_up); ?>"/>
			</label>
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_ANN_DATE_DOWN'); ?>
				<input type="datetime-local" name="publish_down" value="<?php echo $local($a->publish_down); ?>"/>
			</label>
		</div>
		<div class="kb-form-options">
			<input type="hidden" name="showdate" value="0"/>
			<label class="kb-check"><input type="checkbox" name="showdate" value="1"<?php echo !$a->exists() || $a->showdate ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_ANN_SHOWDATE'); ?></label>
			<input type="hidden" name="published" value="0"/>
			<label class="kb-check"><input type="checkbox" name="published" value="1"<?php echo !$a->exists() || $a->published ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_ANN_PUBLISH'); ?></label>
		</div>
	</div>

	<div class="kb-toolbar">
		<a class="kb-btn kb-btn--ghost" href="<?php echo $this->escape($cancel); ?>"><?php echo Text::_('COM_KUNENA_CANCEL'); ?></a>
		<button type="submit" class="kb-btn kb-btn--primary"><?php echo Text::_('COM_KUNENA_SAVE'); ?></button>
	</div>
</form>

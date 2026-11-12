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

// Announcements; moderators get the checkboxes, the bulk actions and "Add"
$options  = $this->getOptions();
$template = KunenaFactory::getTemplate();
?>
<div class="kb-title-row">
	<div class="kb-title"><h1><?php echo Text::_('COM_KUNENA_ANN_ANNOUNCEMENTS'); ?></h1></div>
	<?php if (!empty($options)) : ?>
		<div class="kb-toolbar-group">
			<a class="kb-btn kb-btn--primary" href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=announcement&layout=create'); ?>"><?php echo $template->icon('plus-lg') . ' ' . Text::_('COM_KUNENA_ANNOUNCEMENT_ACTIONS_LABEL_ADD'); ?></a>
		</div>
	<?php endif; ?>
</div>

<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=announcement'); ?>" method="post" id="adminForm" data-kb-bulk>
	<?php echo HTMLHelper::_('form.token'); ?>
	<?php if (empty($this->announcements)) : ?>
		<p class="kb-empty"><?php echo Text::_('KUNENA_BLACKADDER_NO_ANNOUNCEMENTS'); ?></p>
	<?php else : ?>
		<div class="kb-table kb-feed">
			<?php foreach ($this->announcements as $row => $announcement) {
			    echo $this->subLayout('Announcement/List/Row')
			        ->set('announcement', $announcement)
			        ->set('row', $row)
			        ->set('checkbox', !empty($options));
			} ?>
			<?php if (!empty($options)) : ?>
				<div class="kb-bulk">
					<span><?php echo Text::_('KUNENA_BLACKADDER_SELECTED'); ?></span>
					<?php echo HTMLHelper::_('select.genericlist', $options, 'task', 'aria-label="' . Text::_('COM_KUNENA_BULK_CHOOSE_ACTION') . '"', 'value', 'text', 0, 'kchecktask'); ?>
					<button class="kb-btn kb-btn--sm" type="submit" name="kcheckgo"><?php echo Text::_('COM_KUNENA_GO'); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination)->set('display', true); ?>
	<?php endif; ?>
</form>

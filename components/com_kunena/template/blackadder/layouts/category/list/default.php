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


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// A user's subscribed categories (profile tab), with unsubscribe as a bulk action
$kbCheck = !empty($this->actions) && !empty($this->categories);
?>
<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=category'); ?>" method="post" id="kcategoryform" data-kb-bulk>
	<input type="hidden" name="userid" value="<?php echo (int) $this->user->userid; ?>"/>
	<?php echo HTMLHelper::_('form.token'); ?>

	<h3 class="kb-subhead"><?php echo $this->escape($this->headerText); ?> <span class="kb-muted kb-num"><?php echo (int) $this->pagination->total; ?></span></h3>

	<?php if (empty($this->categories)) : ?>
		<p class="kb-empty"><?php echo Text::_('COM_KUNENA_CATEGORY_SUBSCRIPTIONS_NONE'); ?></p>
	<?php else : ?>
		<div class="kb-table kb-subs<?php echo $kbCheck ? ' kb-subs--check' : ''; ?>">
			<?php if ($kbCheck) : ?>
				<div class="kb-table-head">
					<span><?php echo Text::_('KUNENA_BLACKADDER_CATEGORY'); ?></span>
					<span><?php echo Text::_('KUNENA_BLACKADDER_LATEST'); ?></span>
					<span><input class="kb-checkall" type="checkbox" aria-label="<?php echo Text::_('COM_KUNENA_BULK_CHOOSE_ACTION'); ?>"/></span>
				</div>
			<?php endif; ?>
			<?php foreach ($this->categories as $category) {
			    echo $this->subLayout('Category/List/Row')
			        ->set('category', $category)
			        ->set('config', $this->config)
			        ->set('checkbox', $kbCheck);
			} ?>
			<?php if ($kbCheck) : ?>
				<div class="kb-bulk">
					<?php echo HTMLHelper::_('select.genericlist', $this->actions, 'task', 'aria-label="' . Text::_('COM_KUNENA_BULK_CHOOSE_ACTION') . '"', 'value', 'text', 0, 'kchecktask'); ?>
					<button class="kb-btn kb-btn--sm" type="submit" name="kcheckgo"><?php echo Text::_('COM_KUNENA_GO'); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination)->set('display', true); ?>
	<?php endif; ?>
</form>

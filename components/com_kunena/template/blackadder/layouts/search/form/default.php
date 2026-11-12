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


use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Search: keywords (and author) up front, the other options in a collapsible section
$template   = KunenaFactory::getTemplate();
$byUser     = $this->config->pubprofile || !Factory::getUser()->guest;
$atDate     = (string) $this->state->get('query.searchatdate');
$hasOptions = $atDate !== '' || $this->state->get('query.show') || $this->state->get('query.date', 365) != 365;
?>
<div class="kb-title"><h1><?php echo Text::_('COM_KUNENA_SEARCH_ADVSEARCH'); ?></h1></div>

<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=search'); ?>" method="post" class="kb-card kb-form kb-search-form">
	<input type="hidden" name="task" value="results"/>
	<?php echo HTMLHelper::_('form.token'); ?>

	<div class="kb-form-row">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_SEARCH_KEYWORDS'); ?>
			<input type="search" name="query" value="<?php echo $this->escape((string) $this->state->get('searchwords')); ?>" autofocus/>
		</label>
		<label class="kb-field"><?php echo Text::_('KUNENA_BLACKADDER_SEARCH_MATCH'); ?>
			<?php $this->displayModeList('mode', ''); ?>
		</label>
	</div>

	<?php if ($byUser) : ?>
		<div class="kb-form-row">
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_SEARCH_UNAME'); ?>
				<input type="text" name="searchuser" id="kusersearch" autocomplete="off" value="<?php echo $this->escape((string) $this->state->get('query.searchuser')); ?>"/>
			</label>
			<label class="kb-check kb-field-check"><input type="checkbox" name="exactname" value="1"<?php echo $this->state->get('query.exactname') ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_SEARCH_EXACT'); ?></label>
		</div>
	<?php endif; ?>

	<details class="kb-search-options"<?php echo $hasOptions ? ' open' : ''; ?>>
		<summary class="kb-action"><?php echo $template->icon('gear') . ' ' . Text::_('COM_KUNENA_SEARCH_OPTIONS'); ?></summary>
		<div class="kb-search-grid">
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_SEARCH_FIND_POSTS'); ?>
				<span class="kb-inline"><?php $this->displayDateList('date', ''); ?><?php $this->displayBeforeAfterList('beforeafter', ''); ?></span>
			</label>
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_SEARCH_AT_A_SPECIFIC_DATE'); ?>
				<input type="date" name="searchatdate" value="<?php echo $this->escape(preg_match('/^\d{4}-\d{2}-\d{2}/', $atDate) ? substr($atDate, 0, 10) : ''); ?>"/>
			</label>
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_SEARCH_SORTBY'); ?>
				<span class="kb-inline"><?php $this->displaySortByList('sort', ''); ?><?php $this->displayOrderList('order', ''); ?></span>
			</label>
			<label class="kb-field"><?php echo Text::_('KUNENA_BLACKADDER_PER_PAGE'); ?>
				<span class="kb-inline"><?php $this->displayLimitlist('limit', ''); ?></span>
			</label>
			<label class="kb-field kb-search-cats"><?php echo Text::_('COM_KUNENA_SEARCH_SEARCHIN'); ?>
				<?php $this->displayCategoryList('categorylist', 'size="8" multiple="multiple"'); ?>
				<span class="kb-check"><input type="checkbox" name="childforums" value="1"<?php echo $this->state->get('query.childforums') ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_SEARCH_SEARCHIN_CHILDREN'); ?></span>
			</label>
			<?php if ($this->isModerator) : ?>
				<fieldset class="kb-field">
					<legend><?php echo Text::_('COM_KUNENA_SEARCH_SHOW'); ?></legend>
					<?php foreach (array(0 => 'COM_KUNENA_SEARCH_SHOW_NORMAL', 1 => 'COM_KUNENA_SEARCH_SHOW_UNAPPROVED', 2 => 'COM_KUNENA_SEARCH_SHOW_TRASHED') as $value => $label) : ?>
						<label class="kb-check"><input type="radio" name="show" value="<?php echo $value; ?>"<?php echo (int) $this->state->get('query.show') === $value ? ' checked' : ''; ?>/> <?php echo Text::_($label); ?></label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		</div>
	</details>

	<div class="kb-toolbar">
		<span></span>
		<button type="submit" class="kb-btn kb-btn--primary"><?php echo $template->icon('search') . ' ' . Text::_('COM_KUNENA_SEARCH_SEND'); ?></button>
	</div>
</form>

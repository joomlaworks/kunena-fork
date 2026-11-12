<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Page
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

$template = KunenaFactory::getTemplate();
$config   = $template->params;
$kconfig  = KunenaFactory::getConfig();
$query    = \Joomla\CMS\Factory::getApplication()->input->getString('query', '');
$pageSfx  = trim((string) $this->options->get('pageclass_sfx'));
?>
<div id="kunena" class="kb layout<?php echo $pageSfx !== '' ? ' ' . $this->escape($pageSfx) : ''; ?>">

	<div class="kb-brand">
		<a href="<?php echo KunenaRoute::_('index.php?option=com_kunena'); ?>"><?php echo $this->escape($kconfig->board_title); ?></a>
	</div>

	<form class="kb-search" role="search" method="get" action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=search'); ?>">
		<?php echo $template->icon('search'); ?>
		<label class="kb-sr" for="kb-search"><?php echo Text::_('KUNENA_BLACKADDER_SEARCH'); ?></label>
		<input type="search" id="kb-search" name="query" value="<?php echo $this->escape($query); ?>"
		       placeholder="<?php echo Text::_('KUNENA_BLACKADDER_SEARCH'); ?>" autocomplete="off">
		<input type="hidden" name="option" value="com_kunena">
		<input type="hidden" name="view" value="search">
		<input type="hidden" name="task" value="results">
		<input type="hidden" name="Itemid" value="<?php echo (int) KunenaRoute::getItemID(); ?>">
	</form>

	<?php if ($config->get('displayMenu', 1)) : ?>
		<?php echo $this->subLayout('Widget/MenuBar'); ?>
	<?php endif; ?>

	<?php if ($config->get('displayModule')) : ?>
		<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_top'); ?>
	<?php endif; ?>

	<?php if ($config->get('displayBreadcrumb', 1)) : ?>
		<?php echo $this->subLayout('Widget/Breadcrumb')->set('breadcrumb', $this->breadcrumb); ?>
	<?php endif; ?>

	<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_announcement'); ?>

	<?php if ($config->get('displayAnnouncement', 1)) : ?>
		<?php echo $this->subRequest('Widget/Announcement'); ?>
	<?php endif; ?>

	<div class="kb-stack kb-content">
		<?php echo $this->content; ?>
	</div>

	<?php if ($config->get('displayModule')) : ?>
		<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_bottom'); ?>
	<?php endif; ?>

	<?php if ($config->get('displayFooter', 1)) : ?>
		<?php echo $this->subLayout('Widget/Footer'); ?>
	<?php endif; ?>
</div>

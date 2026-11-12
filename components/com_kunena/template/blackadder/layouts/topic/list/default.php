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


use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Recent, unread, own and other thread lists (view=topics, view=user's topics)
$input    = Factory::getApplication()->input;
$view     = $input->getWord('view');
$layout   = $input->getWord('layout');
$template = KunenaFactory::getTemplate();
$kbCheck  = !empty($this->actions);
$counter  = 2;
?>
<?php if ($view !== 'user') : ?>
<div class="kb-title-row">
	<div class="kb-title">
		<h1>
			<?php echo $this->escape($this->headerText); ?>
			<?php if ($layout !== 'unread' && $this->pagination->total) : ?>
				<small><?php echo KunenaForumCategory::getInstance()->totalCount($this->pagination->total); ?></small>
			<?php endif; ?>
		</h1>
	</div>
	<?php if ($view !== 'user') : ?>
		<form class="kb-toolbar-group" action="<?php echo $this->escape(Uri::getInstance()->toString()); ?>" id="timeselect" name="timeselect" method="post">
			<?php $this->displayTimeFilter('sel', 'data-kb-autosubmit aria-label="' . Text::_('COM_KUNENA_SHOW_SELECT_ITEMS') . '"'); ?>
			<?php echo HTMLHelper::_('form.token'); ?>
		</form>
	<?php endif; ?>
</div>
<?php endif; ?>

<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topics'); ?>" method="post" id="ktopicsform" data-kb-bulk>
	<?php echo HTMLHelper::_('form.token'); ?>
	<?php if ($view === 'user' && !empty($this->user)) : ?>
		<input type="hidden" name="userid" value="<?php echo (int) $this->user->userid; ?>"/>
	<?php endif; ?>

	<?php if (empty($this->topics)) : ?>
		<p class="kb-empty"><?php echo Text::_('COM_KUNENA_VIEW_NO_TOPICS'); ?></p>
	<?php else : ?>
		<div class="kb-table kb-topics<?php echo $kbCheck ? ' kb-topics--check' : ''; ?>">
			<?php include __DIR__ . '/head.php'; ?>
			<?php foreach ($this->topics as $i => $topic) {
			    echo $this->subLayout('Topic/Row')
			        ->set('topic', $topic)
			        ->set('position', 'kunena_topic_' . $i)
			        ->set('checkbox', $kbCheck);

			    if ($template->params->get('displayModule')) {
			        echo $this->subLayout('Widget/Module')->set('position', 'kunena_topic_' . $counter++);
			    }
			} ?>
			<?php if ($kbCheck) {
			    $kbActions = $this->actions;
			    $kbMove    = isset($this->actions['move']);
			    include __DIR__ . '/bulk.php';
			} ?>
		</div>
	<?php endif; ?>
</form>

<?php if (!empty($this->topics) && !empty($this->moreUri)) : ?>
	<p class="kb-col-c"><?php echo HTMLHelper::_('kunenaforum.link', $this->moreUri, Text::_('COM_KUNENA_MORE'), null, 'kb-btn', 'follow'); ?></p>
<?php endif; ?>

<?php if ($view !== 'user' || empty($this->moreUri)) : ?>
	<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination->setDisplayedPages(5))->set('display', true); ?>
<?php endif; ?>

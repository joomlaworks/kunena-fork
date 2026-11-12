<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Message
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

// Lists of posts (recent posts, a user's posts, thanks given/received...): a feed of posts with an excerpt each
$view     = Factory::getApplication()->input->getWord('view');
$embedded = $view === 'user';
$kbCheck  = !empty($this->actions);
?>
<?php if (!$embedded) : ?>
	<div class="kb-title-row">
		<div class="kb-title">
			<h1>
				<?php echo $this->escape($this->headerText); ?>
				<?php if ($this->pagination->total) : ?>
					<small><?php echo Text::sprintf($this->messagemore, $this->formatLargeNumber($this->pagination->total)); ?></small>
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

<?php if (empty($this->messages)) : ?>
	<p class="kb-empty"><?php echo Text::_('COM_KUNENA_NO_POSTS'); ?></p>
<?php else : ?>
	<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topics'); ?>" method="post" id="ktopicsform" data-kb-bulk>
		<?php echo HTMLHelper::_('form.token'); ?>
		<div class="kb-table kb-feed">
			<?php foreach ($this->messages as $i => $message) {
			    echo $this->subLayout('Message/Row')
			        ->set('message', $message)
			        ->set('position', $i)
			        ->set('checkbox', $kbCheck);
			} ?>
			<?php if ($kbCheck) {
			    $kbActions = $this->actions;
			    $kbMove    = isset($this->actions['move']);
			    include __DIR__ . '/../../topic/list/bulk.php';
			} ?>
		</div>
	</form>

	<?php if (!empty($this->moreUri)) : ?>
		<p class="kb-col-c"><?php echo HTMLHelper::_('kunenaforum.link', $this->moreUri, Text::_('COM_KUNENA_MORE'), null, 'kb-btn', 'nofollow'); ?></p>
	<?php endif; ?>

	<?php if ($view !== 'user' || empty($this->moreUri)) : ?>
	<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination->setDisplayedPages(5))->set('display', true); ?>
<?php endif; ?>
<?php endif; ?>

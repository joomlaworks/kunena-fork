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

/*
 * The threads of a category, under its title and subcategories (Category/Index): toolbar (new topic, mark read,
 * subscribe), the list, moderators' bulk actions, pagination at the bottom.
 */
$categoryActions = $this->getCategoryActions();
$template        = KunenaFactory::getTemplate();
$kbCheck         = !empty($this->topicActions);
$create          = !empty($categoryActions['create']) ? $categoryActions['create'] : '';

if ($categoryActions) {
    unset($categoryActions['create']);
}
?>
<?php if ($this->category->headerdesc) : ?>
	<div class="kb-alert"><?php echo $this->category->displayField('headerdesc'); ?></div>
<?php endif; ?>

<?php if (!$this->category->isSection()) : ?>
	<div class="kb-toolbar">
		<div class="kb-toolbar-group"><?php echo $categoryActions ? implode('', $categoryActions) : ''; ?></div>
		<div class="kb-toolbar-group"><?php echo $create; ?></div>
	</div>

	<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena'); ?>" method="post" id="ktopicsform" data-kb-bulk>
		<input type="hidden" name="view" value="topics"/>
		<?php echo HTMLHelper::_('form.token'); ?>

		<?php if (empty($this->topics)) : ?>
			<p class="kb-empty"><?php echo Text::_('KUNENA_BLACKADDER_NO_TOPICS_YET'); ?></p>
		<?php else : ?>
			<div class="kb-table kb-topics<?php echo $kbCheck ? ' kb-topics--check' : ''; ?>">
				<?php include __DIR__ . '/../../topic/list/head.php'; ?>
				<?php
                $previous = null;

		    foreach ($this->topics as $position => $topic) {
		        echo $this->subLayout('Topic/Row')
		            ->set('topic', $topic)
		            ->set('spacing', $previous && $previous->ordering != $topic->ordering)
		            ->set('position', 'kunena_topic_' . $position)
		            ->set('checkbox', $kbCheck)
		            ->setLayout('category');
		        $previous = $topic;
		    }

		    if ($kbCheck) {
		        $kbActions = $this->topicActions;
		        $kbMove    = !empty($this->actionMove);
		        include __DIR__ . '/../../topic/list/bulk.php';
		    }
		    ?>
			</div>
		<?php endif; ?>
	</form>

	<?php if (!empty($this->topics) && !empty($this->moreUri)) : ?>
		<p class="kb-col-c"><?php echo HTMLHelper::_('kunenaforum.link', $this->moreUri, Text::_('COM_KUNENA_MORE'), null, 'kb-btn', 'follow'); ?></p>
	<?php endif; ?>

	<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination)->set('display', true); ?>

	<?php if ($template->params->get('writeaccess')) : ?>
		<div class="kb-alert"><?php echo $this->subLayout('Widget/Writeaccess')->set('id', $this->category->id); ?></div>
	<?php endif; ?>
<?php endif; ?>

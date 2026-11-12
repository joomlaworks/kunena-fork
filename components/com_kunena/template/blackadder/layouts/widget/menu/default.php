<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Widget
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// The forum's menu (Kunena's menu items): first level in the bar, children in a menu
$items = array();

foreach ($this->list as $item) {
    if ($item->params->get('menu_show', 1) == 0) {
        continue;
    }

    $items[] = $item;
}

$template = KunenaFactory::getTemplate();
$count    = count($items);
?>
<ul class="kb-menu-items">
	<?php // Moderators, first in the bar: the posts awaiting approval in every category they may approve, when there are any
    $pending = KunenaTemplateBlackadder::pendingApproval();
$input   = \Joomla\CMS\Factory::getApplication()->input; ?>
	<?php if ($pending) : ?>
		<li class="kb-menu-pending<?php echo $input->getWord('mode') === 'unapproved' && $input->getWord('layout') === 'posts' ? ' current' : ''; ?>">
			<a href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topics&layout=posts&mode=unapproved&userid=0'); ?>"><?php echo \Joomla\CMS\Language\Text::_('KUNENA_BLACKADDER_AWAITING_APPROVAL'); ?> <span class="kb-badge"><?php echo $pending; ?></span></a>
		</li>
	<?php endif; ?>
	<?php for ($i = 0; $i < $count; $i++) :
	    $item = $items[$i];

	    if ($item->level > $items[0]->level) {
	        continue;
	    }

	    $class = array('item-' . (int) $item->id);

	    if ($item->id == $this->active_id) {
	        $class[] = 'current';
	    }

	    if (in_array($item->id, (array) $this->path)) {
	        $class[] = 'active';
	    }

	    // Children of this item
	    $children = array();

	    for ($j = $i + 1; $j < $count && $items[$j]->level > $item->level; $j++) {
	        $children[] = $items[$j];
	    }

	    $link = $item->type === 'separator' || $item->type === 'heading' ? '' : $item->flink;
	    ?>
		<li class="<?php echo implode(' ', $class); ?>">
			<?php if ($children) : ?>
				<details class="kb-dropdown kb-dropdown--left">
					<summary><a><?php echo $this->escape($item->title); ?> <?php echo $template->icon('chevron-down'); ?></a></summary>
					<div class="kb-dropdown-menu">
						<?php if ($link) : ?>
							<a href="<?php echo $link; ?>"><?php echo $this->escape($item->title); ?></a>
						<?php endif; ?>
						<?php foreach ($children as $child) : ?>
							<?php if ($child->type !== 'separator' && $child->type !== 'heading') : ?>
								<a href="<?php echo $child->flink; ?>"<?php echo $child->id == $this->active_id ? ' aria-current="page"' : ''; ?>><?php echo $this->escape($child->title); ?></a>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				</details>
			<?php elseif ($link) : ?>
				<a href="<?php echo $link; ?>"<?php echo $item->id == $this->active_id ? ' aria-current="page"' : ''; ?>><?php echo $this->escape($item->title); ?></a>
			<?php else : ?>
				<a><?php echo $this->escape($item->title); ?></a>
			<?php endif; ?>
		</li>
	<?php endfor; ?>
</ul>

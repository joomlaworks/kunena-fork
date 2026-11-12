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


use Joomla\CMS\Language\Text;

// Pages: previous, numbers (with gaps), next; centred at the bottom of lists and threads
$data  = $this->pagination->getData();
$count = count($data->pages);

if ($count <= 1) {
    return;
}

$template = KunenaFactory::getTemplate();
$last     = 0;
?>
<div role="navigation" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_PAGINATION'); ?>">
	<ul class="kb-pagination">
		<?php if ($data->previous->base !== null) : ?>
			<li><a href="<?php echo $data->previous->link; ?>" rel="prev" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_PREVIOUS'); ?>"><?php echo $template->icon('chevron-left'); ?></a></li>
		<?php endif; ?>
		<?php foreach ($data->pages as $k => $item) : ?>
			<?php if ($last + 1 != $k) : ?><li><span aria-hidden="true">…</span></li><?php endif; ?>
			<?php $last = $k; ?>
			<li>
				<?php if ($item->base !== null) : ?>
					<a href="<?php echo $item->link; ?>"><?php echo $item->text; ?></a>
				<?php else : ?>
					<span aria-current="page"><?php echo $item->text; ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
		<?php if ($data->next->base !== null) : ?>
			<li><a href="<?php echo $data->next->link; ?>" rel="next" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_NEXT'); ?>"><?php echo $template->icon('chevron-right'); ?></a></li>
		<?php endif; ?>
	</ul>
</div>

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

// The pages of a thread, inline in thread lists
$data = $this->pagination->getData();

if (!$data->pages || count($data->pages) <= 1) {
    return;
}

$last = 0;
?>
<span class="kb-mini-pages" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_PAGES'); ?>">
	<?php foreach ($data->pages as $k => $item) : ?>
		<?php if ($last + 1 != $k) : ?><span aria-hidden="true">…</span><?php endif; ?>
		<?php $last = $k; ?>
		<?php if ($item->base !== null || !empty($item->link)) : ?>
			<a href="<?php echo $item->link; ?>"><?php echo $item->text; ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</span>

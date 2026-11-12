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


use Joomla\CMS\Language\Text;

// Header row of a thread list (included by topic/list and category/item; $kbCheck = moderator checkboxes)
?>
<div class="kb-table-head" aria-hidden="true">
	<span><?php echo Text::_('KUNENA_BLACKADDER_TOPIC'); ?></span>
	<span><?php echo Text::_('KUNENA_BLACKADDER_POSTERS'); ?></span>
	<span class="kb-col-r"><?php echo Text::_('KUNENA_BLACKADDER_REPLIES'); ?></span>
	<span class="kb-col-r"><?php echo Text::_('KUNENA_BLACKADDER_VIEWS'); ?></span>
	<span class="kb-col-r"><?php echo Text::_('KUNENA_BLACKADDER_ACTIVITY'); ?></span>
	<?php if (!empty($kbCheck)) : ?>
		<span><input class="kb-checkall" type="checkbox" aria-label="<?php echo Text::_('COM_KUNENA_BULK_CHOOSE_ACTION'); ?>"/></span>
	<?php endif; ?>
</div>

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.BBCode
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// [hide]: text only members see
?>
<div class="kb-hidden-text">
	<?php if ($this->me->exists()) : ?>
		<small><?php echo Text::_('COM_KUNENA_BBCODE_HIDE_IN_MESSAGE'); ?></small>
		<div><?php echo $this->content; ?></div>
	<?php else : ?>
		<small><?php echo Text::_('COM_KUNENA_BBCODE_HIDDENTEXT'); ?></small>
	<?php endif; ?>
</div>

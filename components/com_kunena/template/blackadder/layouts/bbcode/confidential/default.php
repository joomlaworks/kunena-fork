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

// [confidential]: text only the author and moderators see
?>
<div class="kb-hidden-text">
	<small><?php echo Text::_('COM_KUNENA_BBCODE_CONFIDENTIAL_TEXT'); ?></small>
	<div><?php echo $this->content; ?></div>
</div>

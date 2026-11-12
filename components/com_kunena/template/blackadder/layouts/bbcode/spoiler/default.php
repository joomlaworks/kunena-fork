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

// [spoiler="title"]text[/spoiler]: closed until opened (no JavaScript needed)
?>
<details class="kb-spoiler">
	<summary><?php echo $this->title ?: Text::_('COM_KUNENA_LIB_BBCODE_SPOILER_EXPAND'); ?></summary>
	<div><?php echo $this->content; ?></div>
</details>

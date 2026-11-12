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


use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Categories are managed in Kunena's administrator (this frontend page never listed them)
?>
<div class="kb-title"><h1><?php echo Text::_('COM_KUNENA_MENU_CATEGORY_MANAGE'); ?></h1></div>
<p class="kb-alert">
	<?php echo Text::_('KUNENA_BLACKADDER_MANAGE_IN_ADMIN'); ?>
	<a href="<?php echo $this->escape(Uri::root() . 'administrator/index.php?option=com_kunena&view=categories'); ?>"><?php echo Text::_('KUNENA_BLACKADDER_OPEN_ADMIN'); ?></a>
</p>

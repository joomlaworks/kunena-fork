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


// A custom page (menu item "Kunena: Custom Page"): its title and text, with the custom module positions
?>
<?php if ($this->header) : ?>
	<div class="kb-title"><h1><?php echo $this->header; ?></h1></div>
<?php endif; ?>
<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_custom_top'); ?>
<div class="kb-prose"><?php echo $this->body; ?></div>
<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_custom_bottom'); ?>

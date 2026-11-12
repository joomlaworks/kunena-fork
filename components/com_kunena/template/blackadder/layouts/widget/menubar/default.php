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

?>
<div role="navigation" class="kb-menubar" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_MENU'); ?>">
	<div class="kb-menu">
		<?php echo $this->subRequest('Widget/Menu'); ?>
		<div class="kb-account">
			<?php echo $this->subRequest('Widget/Login'); ?>
		</div>
	</div>
</div>

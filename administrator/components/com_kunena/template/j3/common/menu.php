<?php
/**
 * Kunena Component
 * @package         Kunena.Administrator.Template
 * @subpackage      Common
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$view = Factory::getApplication()->input->getCmd('view', 'cpanel');

// 5.5.0: the entries come from common/items.php (shared with the dashboard), in two groups with a divider between them
$kunenaMenuGroups = include __DIR__ . '/items.php';
?>
<!-- Main navigation -->
<ul class="nav nav-list">
	<li<?php echo $view == 'cpanel' ? ' class="active"' : ''; ?>>
		<a href="<?php echo Route::_('index.php?option=com_kunena'); ?>">
			<i class="icon-dashboard"></i> <?php echo Text::_('COM_KUNENA_MENU_DESC_DASHBOARD'); ?>
		</a>
	</li>
	<?php foreach ($kunenaMenuGroups as $kunenaMenuGroup => $kunenaMenuItems) : ?>
		<?php if ($kunenaMenuGroup !== 'content') : ?>
			<li class="divider kunena-menu-divider" role="separator"></li>
		<?php endif; ?>
		<?php foreach ($kunenaMenuItems as $kunenaMenuView => $kunenaMenuEntry) : ?>
			<li<?php echo $view === $kunenaMenuView || in_array($view, $kunenaMenuEntry[2], true) ? ' class="active"' : ''; ?>>
				<a href="<?php echo Route::_('index.php?option=com_kunena&view=' . $kunenaMenuView); ?>">
					<i class="icon-<?php echo $kunenaMenuEntry[1]; ?>"></i> <?php echo Text::_($kunenaMenuEntry[0]); ?>
				</a>
			</li>
		<?php endforeach; ?>
	<?php endforeach; ?>
</ul>
<!-- /Main navigation -->
<?php unset($kunenaMenuGroups, $kunenaMenuGroup, $kunenaMenuItems, $kunenaMenuView, $kunenaMenuEntry);

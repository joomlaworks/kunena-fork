<?php
/**
 * Kunena Component
 * @package         Kunena.Administrator.Template
 * @subpackage      CPanel
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();
use Joomla\CMS\Language\Text;

?>
<div id="kunena" class="admin override">
	<div id="j-sidebar-container" class="span2">
		<div id="sidebar">
			<div class="sidebar-nav"><?php include KPATH_ADMIN . '/template/j3/common/menu.php'; ?></div>
		</div>
	</div>
	<div id="j-main-container" class="span10">
		<section class="content-block" role="main">
			<div class="row-fluid kunena-dashboard">
				<div class="span7">
					<div class="well well-small">
						<div class="module-title nav-header"><?php echo Text::_('COM_KUNENA_CPANEL_DESC_WELCOME') ?></div>
						<hr class="hr-condensed">
						<?php // 5.5.0: the same entries as the sidebar (common/items.php), the same two groups?>
						<div id="dashboard-icons" class="btn-group">
							<?php foreach (include KPATH_ADMIN . '/template/j3/common/items.php' as $group => $items) : ?>
								<?php if ($group !== 'content') : ?>
									<hr class="kunena-dashboard-divider" role="separator">
								<?php endif; ?>
								<?php foreach ($items as $item => $entry) : ?>
									<a class="btn" href="index.php?option=com_kunena&amp;view=<?php echo $item; ?>">
										<i class="icon-big icon-<?php echo $entry[1]; ?>"></i><br/>
										<span><?php echo Text::_($entry[0]); ?></span>
									</a>
								<?php endforeach; ?>
							<?php endforeach; ?>
						</div>
						<div class="clearfix"></div>
					</div>
				</div>

				<div class="span5">
					<div class="well well-small">
						<div class="center">
							<img src="components/com_kunena/media/icons/kunena_logo.png"/>
						</div>
						<?php // Values wrapping on several lines stay aligned next to their label?>
						<style>.kunena-about dd { overflow: hidden; }</style>
						<dl class="dl-horizontal kunena-about">
							<hr class="hr-condensed">
							<dt><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_VERSION') ?>:</dt>
							<dd><?php echo strtoupper(KunenaForum::version()); ?> (<a href="https://github.com/joomlaworks/kunena-fork/blob/main/CHANGELOG.md" target="_blank" rel="noopener noreferrer"><?php echo Text::_('COM_KUNENA_DASHBOARD_CHANGELOG'); ?></a>)</dd>
							<hr class="hr-condensed">
							<dt><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_DATE') ?>:</dt>
							<dd><?php echo KunenaForum::versionDate(); ?></dd>
							<hr class="hr-condensed">
							<dt><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_AUTHOR') ?>:</dt>
							<dd><a href="https://www.kunena.org/team" target="_blank" rel="noopener noreferrer">Kunena Team</a>,
								<a href="https://www.joomlaworks.net" target="_blank" rel="noopener noreferrer">JoomlaWorks Ltd.</a> &amp; Contributors</dd>
							<hr class="hr-condensed">
							<dt><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_COPYRIGHT') ?>:</dt>
							<dd>&copy; 2008 - 2026 Kunena Team<br/>&copy; 2026 JoomlaWorks Ltd. &amp; Contributors</dd>
							<hr class="hr-condensed">
							<dt><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_LABEL_LICENSE') ?>:</dt>
							<dd>GNU General Public License</dd>
							<?php // 5.3.0: no kunena.org terms link, update check (kunena.org's update server) or PayPal donation form (it loaded a tracking pixel from paypal.com)?>
						</dl>
					</div>
				</div>
		</section>
	</div>
</div>

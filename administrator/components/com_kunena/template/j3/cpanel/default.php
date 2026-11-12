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
			<div class="row-fluid">
				<div class="span7">
					<div class="well well-small">
						<div class="module-title nav-header"><?php echo Text::_('COM_KUNENA_CPANEL_DESC_WELCOME') ?></div>
						<hr class="hr-condensed">
						<div id="dashboard-icons" class="btn-group">
							<a class="btn" href="index.php?option=com_kunena&view=categories">
								<i class="icon-big icon-list-view"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_CATEGORIES') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=users">
								<i class="icon-big icon-users"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_USERS') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=attachments">
								<i class="icon-big icon-flag-2"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_FILES') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=smilies">
								<i class="icon-big icon-thumbs-up"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_EMOTICONS') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=ranks">
								<i class="icon-big icon-star-2"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_RANKS') ?></span>
							</a>
							<!--<a class="btn" href="index.php?option=com_kunena&view=labels">
								<i class="icon-big icon-tags-2"></i><br/>
								<span><?php // Echo Text::_('COM_KUNENA_A_LABELS_MANAGER')
                            ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=icons">
								<i class="icon-big icon-grid-2"></i><br/>
								<span><?php // Echo Text::_('COM_KUNENA_A_ICONS_MANAGER')
                            ?></span>
							</a>-->
							<a class="btn" href="index.php?option=com_kunena&view=templates">
								<i class="icon-big icon-color-palette"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_TEMPLATES') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=config">
								<i class="icon-big icon-cogs"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_CONFIG') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=plugins">
								<i class="icon-big icon-puzzle"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_PLUGINS') ?></span>
							</a>
							<!--<a class="btn" href="index.php?option=com_kunena&view=email">
								<i class="icon-big icon-mail"></i><br/>
								<span><?php // Echo Text::_('COM_KUNENA_A_EMAIL_MANAGER')
                            ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=blockips">
								<i class="icon-big icon-compass"></i><br/>
								<span><?php // Echo Text::_('COM_KUNENA_A_BLOCKIP_MANAGER')
                            ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=badwords">
								<i class="icon-big icon-smiley-sad-2"></i><br/>
								<span><?php // Echo Text::_('COM_KUNENA_A_BADWORDS_MANAGER')
                            ?></span>
							</a>-->
							<a class="btn" href="index.php?option=com_kunena&view=logs">
								<i class="icon-big icon-search"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_LOG_MANAGER') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=statistics">
								<i class="icon-big icon-chart"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_MENU_STATISTICS') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=tools">
								<i class="icon-big icon-tools"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_TOOLS') ?></span>
							</a>
							<a class="btn" href="index.php?option=com_kunena&view=trash">
								<i class="icon-big icon-trash"></i><br/>
								<span><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_TRASH') ?></span>
							</a>
						</div>
						<div class="clearfix"></div>
					</div>
					<div class="well well-small">
						<div class="module-title nav-header"><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_GETTINGSTARTED') ?></div>
						<hr class="hr-condensed">
						<ul class="pull-left">
							<li><i class="icon icon-question"></i> <a href="https://docs.kunena.org/en/setup"
																	  target="_blank"
																	  rel="noopener noreferrer"><?php echo Text::_('COM_KUNENA_CPANEL_DESC_HOWTOSETUP') ?> </a>
							</li>
							<li><i class="icon icon-question"></i> <a
										href="https://docs.kunena.org/en/setup/sections-categories" target="_blank"
										rel="noopener noreferrer"><?php echo Text::_('COM_KUNENA_CPANEL_DESC_CATEGORIES') ?> </a>
							</li>
							<li><i class="icon icon-question"></i> <a href="https://www.kunena.org/forum"
																	  target="_blank"
																	  rel="noopener noreferrer"><?php echo Text::_('COM_KUNENA_CPANEL_DESC_SUPPORT') ?> </a>
							</li>
						</ul>
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
							<dd><?php echo strtoupper(KunenaForum::version()); ?></dd>
							<hr class="hr-condensed">
							<dt><?php echo Text::_('COM_KUNENA_CPANEL_LABEL_CODENAME') ?>:</dt>
							<dd><?php echo KunenaForum::versionName(); ?></dd>
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

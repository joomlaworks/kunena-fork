<?php
/**
 * Kunena Component
 * @package         Kunena.Administrator.Template
 * @subpackage      Report
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$document = Factory::getDocument();
// 5.3.0: plain JavaScript (MooTools is not available on every Joomla 3 site, e.g. Joomla 3.x UTD)
$document->addScriptDeclaration(
    <<<'JS'
document.addEventListener('DOMContentLoaded', function () {
	var copy = function (id) {
		var text = document.getElementById(id);

		if (!text) {
			return;
		}

		text.select();

		try {
			document.execCommand('copy');
		} catch (err) {
			console.log('Unable to copy');
		}
	};
	var all = document.getElementById('link_sel_all');
	var anonymous = document.getElementById('link_sel_all_complete');

	if (all) {
		all.addEventListener('click', function () {
			copy('report_final');
		});
	}

	if (anonymous) {
		anonymous.addEventListener('click', function () {
			copy('report_final_anonymous');
		});
	}
});
JS
);

HTMLHelper::_('behavior.tooltip');
HTMLHelper::_('behavior.multiselect');
?>

<div id="kunena" class="admin override">
	<div id="j-sidebar-container" class="span2">
		<div id="sidebar">
			<div class="sidebar-nav"><?php include KPATH_ADMIN . '/template/j3/common/menu.php'; ?></div>
		</div>
	</div>
	<div id="j-main-container" class="span10">
		<form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=tools') ?>"
			  method="post" id="adminForm" name="adminForm">
			<input type="hidden" name="task" value=""/>
			<input type="hidden" name="boxchecked" value="1"/>
			<fieldset>
				<legend><i class="icon icon-support"></i> <?php echo Text::_('COM_KUNENA_REPORT_SYSTEM_COMPLETE'); ?>
				</legend>
				<table class="table table-bordered table-striped">
					<tr>
						<td>
							<p><?php echo Text::_('COM_KUNENA_REPORT_SYSTEM_COMPLETE_DESC'); ?></p>
							<p>
								<a href="#" id="link_sel_all" name="link_sel_all" type="button"
								   class="btn btn-small btn-primary"><i
											class="icon icon-signup"></i><?php echo Text::_('COM_KUNENA_REPORT_SELECT_ALL'); ?>
								</a>
							</p>
							<textarea id="report_final" class="input-block-level" name="report_final" cols="80"
									  rows="15"><?php echo $this->escape($this->systemreport); ?></textarea>
						</td>
					</tr>
				</table>
			</fieldset>
			<fieldset>
				<legend><i class="icon icon-support"></i> <?php echo Text::_('COM_KUNENA_REPORT_SYSTEM_ANONYMOUS'); ?>
				</legend>
				<table class="table table-bordered table-striped">
					<tr>
						<td>
							<p><?php echo Text::_('COM_KUNENA_REPORT_SYSTEM_ANONYMOUS_DESC'); ?></p>
							<p>
								<a href="#" id="link_sel_all_complete" name="link_sel_all_complete" type="button"
								   class="btn btn-small btn-primary"><i
											class="icon icon-signup"></i><?php echo Text::_('COM_KUNENA_REPORT_SELECT_ALL'); ?>
								</a>
							</p>
							<textarea id="report_final_anonymous" class="input-block-level"
									  name="report_final_anonymous" cols="80"
									  rows="15"><?php echo $this->escape($this->systemreport_anonymous); ?></textarea>
						</td>
					</tr>
				</table>
			</fieldset>
		</form>
	</div>
	<div class="pull-right small">
		<?php echo KunenaAdminVersion::getLongVersionHTML(); ?>
	</div>
</div>

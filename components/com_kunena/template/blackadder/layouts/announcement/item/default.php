<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Announcement
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// An announcement: title, date, actions, the short and the full text
$announcement = $this->announcement;
$actions      = $this->getActions();
?>
<div role="article" class="kb-stack kb-announcement-page">
	<div class="kb-title">
		<h1><?php echo $announcement->displayField('title'); ?></h1>
		<?php if ($announcement->showdate) : ?>
			<p class="kb-muted" title="<?php echo $announcement->displayField('created', 'ago'); ?>"><?php echo $announcement->displayField('created', 'date_today'); ?></p>
		<?php endif; ?>
	</div>
	<?php if (!empty($actions)) : ?>
		<div class="kb-toolbar-group"><?php echo implode('', $actions); ?></div>
	<?php endif; ?>
	<div class="kb-prose">
		<div><?php echo $announcement->displayField('sdescription'); ?></div>
		<div><?php echo $announcement->displayField('description'); ?></div>
	</div>
</div>

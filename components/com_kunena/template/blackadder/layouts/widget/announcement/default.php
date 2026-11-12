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

$template     = KunenaFactory::getTemplate();
$announcement = $this->announcement;
?>
<div role="region" class="kb-announcement" aria-label="<?php echo Text::_('COM_KUNENA_ANN_ANNOUNCEMENTS'); ?>">
	<h2>
		<?php echo $template->icon('megaphone'); ?>
		<a href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=announcement&layout=list'); ?>"><?php echo $announcement->displayField('title'); ?></a>
		<?php if ($announcement->showdate) : ?><small class="kb-muted"><?php echo $announcement->displayField('created', 'date_today'); ?></small><?php endif; ?>
	</h2>
	<div class="kb-announcement-body">
		<?php echo $announcement->displayField('sdescription'); ?>
		<?php if (!empty($announcement->description)) : ?>
			<a href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=announcement&layout=default&id=' . (int) $announcement->id); ?>"><?php echo Text::_('COM_KUNENA_ANN_READMORE'); ?></a>
		<?php endif; ?>
	</div>
</div>

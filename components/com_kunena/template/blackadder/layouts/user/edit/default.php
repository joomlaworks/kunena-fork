<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.User
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Edit a profile: account, profile, avatar and settings as tabs of one form
$this->profile = KunenaFactory::getUser($this->user->id);
$this->me      = KunenaUserHelper::getMyself();
$tabs          = $this->getTabsEdit();
$template      = KunenaFactory::getTemplate();

if (!(KunenaFactory::getAvatarIntegration() instanceof KunenaAvatarKunena)) {
    unset($tabs['avatar']);
}
?>
<div class="kb-title-row">
	<div class="kb-title"><h1><?php echo Text::_('COM_KUNENA_EDIT') . ': ' . $this->escape($this->profile->getName()); ?></h1></div>
	<div class="kb-toolbar-group"><?php echo $this->profile->getLink($template->icon('arrow-left') . ' ' . Text::_('COM_KUNENA_BACK'), Text::_('COM_KUNENA_BACK'), 'nofollow', '', 'kb-btn kb-btn--sm'); ?></div>
</div>

<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user'); ?>" method="post" enctype="multipart/form-data" id="kuserform" class="kb-form">
	<input type="hidden" name="task" value="save"/>
	<input type="hidden" name="userid" value="<?php echo (int) $this->user->id; ?>"/>
	<?php echo HTMLHelper::_('form.token'); ?>

	<?php echo $this->subLayout('Widget/Tabs')->set('tabs', $tabs); ?>

	<div class="kb-toolbar">
		<?php echo $this->profile->getLink(Text::_('COM_KUNENA_CANCEL'), Text::_('COM_KUNENA_CANCEL'), 'nofollow', '', 'kb-btn kb-btn--ghost'); ?>
		<button class="kb-btn kb-btn--primary" type="submit"><?php echo Text::_('COM_KUNENA_SAVE'); ?></button>
	</div>
</form>

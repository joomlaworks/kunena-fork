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


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

if (!$this->plglogin) {
    return;
}

$template = KunenaFactory::getTemplate();
$login    = KunenaLogin::getInstance();
?>
<button type="button" class="kb-btn kb-btn--ghost" data-kb-login-toggle aria-expanded="false" aria-controls="kb-login">
	<?php echo $template->icon('box-arrow-in-right'); ?> <?php echo Text::_('JLOGIN'); ?> <?php echo $template->icon('chevron-down'); ?>
</button>

<form class="kb-login" id="kb-login" hidden method="post" action="<?php echo KunenaRoute::_('index.php?option=com_kunena'); ?>">
	<input type="hidden" name="view" value="user">
	<input type="hidden" name="task" value="login">
	<?php echo HTMLHelper::_('form.token'); ?>

	<div class="kb-login-fields">
		<label class="kb-field" for="kb-login-username"><?php echo Text::_('JGLOBAL_USERNAME'); ?>
			<input type="text" id="kb-login-username" name="username" autocomplete="username" required>
		</label>
		<label class="kb-field" for="kb-login-password"><?php echo Text::_('JGLOBAL_PASSWORD'); ?>
			<input type="password" id="kb-login-password" name="password" autocomplete="current-password" required>
		</label>
		<?php if ($login->getTwoFactorMethods() > 1) : ?>
			<label class="kb-field" for="kb-login-secretkey"><?php echo Text::_('COM_KUNENA_LOGIN_SECRETKEY'); ?>
				<input type="text" id="kb-login-secretkey" name="secretkey" autocomplete="one-time-code">
			</label>
		<?php endif; ?>
		<button type="submit" class="kb-btn kb-btn--primary"><?php echo Text::_('JLOGIN'); ?></button>
	</div>

	<div class="kb-login-foot">
		<?php if ($this->rememberMe) : ?>
			<label class="kb-check" for="kb-login-remember">
				<input type="checkbox" id="kb-login-remember" name="remember" value="yes"> <?php echo Text::_('JGLOBAL_REMEMBER_ME'); ?>
			</label>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<div class="kb-login-links">
			<?php if ($this->resetPasswordUrl) : ?>
				<a href="<?php echo $this->resetPasswordUrl; ?>"><?php echo Text::_('COM_KUNENA_PROFILEBOX_FORGOT_PASSWORD'); ?></a>
			<?php endif; ?>
			<?php if ($this->remindUsernameUrl) : ?>
				<a href="<?php echo $this->remindUsernameUrl; ?>"><?php echo Text::_('COM_KUNENA_PROFILEBOX_FORGOT_USERNAME'); ?></a>
			<?php endif; ?>
			<?php if ($this->registrationUrl) : ?>
				<a href="<?php echo $this->registrationUrl; ?>"><?php echo Text::_('COM_KUNENA_PROFILEBOX_CREATE_ACCOUNT'); ?></a>
			<?php endif; ?>
		</div>
	</div>

	<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_login'); ?>
</form>

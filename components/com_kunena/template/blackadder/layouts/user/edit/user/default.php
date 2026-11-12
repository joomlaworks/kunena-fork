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


use Joomla\CMS\Language\Text;

// Account: username, name, e-mail, password, Joomla's user settings (editor, language, time zone...)
?>
<div class="kb-card">
	<div class="kb-form-row">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_UNAME'); ?>
			<input type="text" name="username" autocomplete="username" value="<?php echo $this->escape((string) $this->user->get('username')); ?>"<?php echo !$this->changeUsername ? ' disabled' : ''; ?>/>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_REALNAME'); ?>
			<input type="text" name="name" required autocomplete="name" value="<?php echo $this->escape((string) $this->user->get('name')); ?>"/>
		</label>
	</div>
	<label class="kb-field"><?php echo Text::_('COM_KUNENA_USRL_EMAIL'); ?>
		<input type="email" name="email" required autocomplete="email" value="<?php echo $this->escape((string) $this->user->get('email')); ?>"/>
	</label>
	<?php if ($this->user->get('password')) : ?>
		<div class="kb-form-row">
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_PASS'); ?>
				<input type="password" name="password" autocomplete="new-password" value=""/>
			</label>
			<label class="kb-field"><?php echo Text::_('COM_KUNENA_VPASS'); ?>
				<input type="password" name="password2" autocomplete="new-password" value=""/>
			</label>
		</div>
	<?php endif; ?>
</div>

<?php if (!empty($this->frontendForm)) : ?>
	<div class="kb-card">
		<h3 class="kb-subhead"><?php echo Text::_('COM_KUNENA_GLOBAL_SETTINGS'); ?></h3>
		<?php foreach ($this->frontendForm as $field) : ?>
			<div class="kb-field kb-joomla-field"><?php echo $field->label; ?><?php echo $field->input; ?></div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>

<?php if (KunenaLogin::getTwoFactorMethods() > 1) : ?>
	<p><a class="kb-action" href="<?php echo \Joomla\CMS\Router\Route::_('index.php?option=com_users&view=profile&layout=edit'); ?>"><?php echo KunenaFactory::getTemplate()->icon('shield-check') . ' ' . Text::_('COM_KUNENA_2FA_SETTINGS'); ?></a></p>
<?php endif; ?>

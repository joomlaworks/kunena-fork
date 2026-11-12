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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// Shown instead of a page that needs a login: "Log in" opens the header's login panel (Joomla's login page without
// JavaScript); "create an account" when registration is open
$login    = KunenaLogin::getInstance();
$register = $login ? $login->getRegistrationURL() : '';
$loginUrl = Route::_('index.php?option=com_users&view=login&return=' . base64_encode((string) Uri::getInstance()));
?>
<div class="kb-card kb-login-required">
	<h1><?php echo Text::_('KUNENA_BLACKADDER_LOGIN_REQUIRED'); ?></h1>
	<p class="kb-muted"><?php echo Text::_('COM_KUNENA_LOGIN_FORUM'); ?></p>
	<div class="kb-toolbar-group">
		<a class="kb-btn kb-btn--primary" href="<?php echo $this->escape($loginUrl); ?>" data-kb-open-login><?php echo KunenaFactory::getTemplate()->icon('box-arrow-in-right') . ' ' . Text::_('JLOGIN'); ?></a>
		<?php if ($register) : ?>
			<a class="kb-btn" href="<?php echo $this->escape($register); ?>"><?php echo Text::_('COM_KUNENA_PROFILEBOX_CREATE_ACCOUNT'); ?></a>
		<?php endif; ?>
	</div>
</div>

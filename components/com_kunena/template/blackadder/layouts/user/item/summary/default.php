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

// The top of a profile: avatar, name, role and status, actions; numbers; details; social links; about me, signature
$profile      = $this->profile;
$config       = $this->config;
$template     = KunenaFactory::getTemplate();
$me           = KunenaUserHelper::getMyself();
$banInfo      = $config->showbannedreason ? KunenaUserBan::getInstanceByUserid($profile->userid) : null;
$private      = KunenaFactory::getPrivateMessaging();
$websiteURL   = $profile->getWebsiteURL();
$websiteName  = $profile->getWebsiteName();
$personalText = $profile->getPersonalText();
$signature    = $profile->getSignature();
$activity     = KunenaFactory::getActivityIntegration();
$points       = $activity->getUserPoints($profile->userid);
$medals       = $activity->getUserMedals($profile->userid);
$role         = $profile->getType(0, true);
$stats        = array();
$details      = array();

if (!empty($profile->posts)) {
    $stats[Text::_('COM_KUNENA_POSTS')] = (int) $profile->posts;
}

if (!empty($profile->thankyou) && $config->showthankyou) {
    $stats[Text::_('COM_KUNENA_THANK_YOU_RECEIVED')] = (int) $profile->thankyou;
}

if (!empty($profile->uhits)) {
    $stats[Text::_('COM_KUNENA_PROFILE_VIEWS')] = (int) $profile->uhits;
}

if ($config->showuserstats && $config->showkarma && !empty($profile->karma)) {
    $stats[Text::_('COM_KUNENA_KARMA')] = (int) $profile->karma;
}

if (!empty($points)) {
    $stats[rtrim(Text::_('COM_KUNENA_AUP_POINTS'), ': ')] = $points;
}

if ($config->showuserstats && $config->showranking) {
    $details[Text::_('COM_KUNENA_MYPROFILE_RANK')] = $this->escape((string) $profile->getRank(0, 'title')) . ' ' . $profile->getRank(0, 'image');
}

if ($config->userlist_joindate || $me->isModerator()) {
    $details[Text::_('COM_KUNENA_MYPROFILE_REGISTERDATE')] = '<span title="' . $profile->getRegisterDate()->toKunena('ago') . '">' . $profile->getRegisterDate()->toKunena('date_today', 'utc') . '</span>';
}

if ($config->userlist_lastvisitdate || $me->isModerator()) {
    $details[Text::_('COM_KUNENA_MYPROFILE_LASTLOGIN')] = $profile->getLastVisitDate()->toSpan('config_post_dateformat', 'ago');
}

$details[Text::_('COM_KUNENA_MYPROFILE_LOCAL_TIME')] = $profile->getTime()->toKunena('time') . ' <span class="kb-muted">(UTC ' . $profile->getTime()->toTimezone() . ')</span>';

if (!empty($profile->location)) {
    $details[Text::_('COM_KUNENA_MYPROFILE_LOCATION')] = '<a href="https://www.openstreetmap.org/search?query=' . rawurlencode($profile->location) . '" target="_blank" rel="nofollow noopener noreferrer">' . $this->escape($profile->location) . '</a>';
}

if ($profile->getGender() >= 1) {
    $details[Text::_('COM_KUNENA_MYPROFILE_GENDER')] = $profile->getGender();
}

if ($profile->birthdate >= '1901-01-01') {
    $details[Text::_('COM_KUNENA_MYPROFILE_BIRTHDATE')] = KunenaDate::getInstance($profile->birthdate)->toSpan('date', 'ago', 'utc');
}

if (!empty($medals)) {
    $details[rtrim(Text::_('COM_KUNENA_AUP_MEDALS'), ': ')] = implode(' ', $medals);
}

if ($banInfo && $banInfo->reason_public) {
    $details[Text::_('COM_KUNENA_MYPROFILE_BANINFO')] = $this->escape($banInfo->reason_public);
}
?>
<div class="kb-profile">
	<div class="kb-profile-head">
		<?php echo $template->avatar($profile, 'xl'); ?>
		<div class="kb-profile-name">
			<h1><?php echo $this->escape($profile->getName()); ?></h1>
			<div class="kb-meta">
				<?php if (in_array($role, array('admin', 'localadmin', 'globalmod', 'moderator', 'banned', 'blocked'), true)) : ?>
					<span class="kb-chip kb-chip--role"><?php echo Text::_($profile->getType()); ?></span>
				<?php endif; ?>
				<?php if ($config->user_status) : ?>
					<?php echo $this->subLayout('User/Item/Status')->set('user', $profile); ?>
				<?php endif; ?>
				<?php if (!empty($profile->username) && $profile->username !== $profile->getName()) : ?>
					<span>@<?php echo $this->escape($profile->username); ?></span>
				<?php endif; ?>
			</div>
		</div>
		<div class="kb-toolbar-group kb-profile-actions">
			<?php if ($profile->isAuthorised('edit')) : ?>
				<?php echo $profile->getLink($template->icon('pencil') . '<span>' . Text::_('COM_KUNENA_EDIT') . '</span>', Text::_('COM_KUNENA_EDIT'), 'nofollow', 'edit', 'kb-btn kb-btn--sm'); ?>
			<?php endif; ?>
			<?php if ($private) : ?>
				<?php echo $private->shownewIcon($profile->userid, 'kb-btn kb-btn--sm', ''); ?>
			<?php endif; ?>
			<?php if ($this->candisplaymail) : ?>
				<a class="kb-btn kb-btn--sm" href="mailto:<?php echo $this->escape($profile->email); ?>" rel="nofollow"><?php echo $template->icon('envelope', Text::_('COM_KUNENA_MYPROFILE_EMAIL')); ?></a>
			<?php endif; ?>
			<?php if (!empty($websiteURL)) : ?>
				<a class="kb-btn kb-btn--sm" rel="nofollow noopener noreferrer" target="_blank" href="<?php echo htmlspecialchars($websiteURL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"><?php echo $template->icon('globe'); ?><span><?php echo $websiteName ? $this->escape($websiteName) : Text::_('COM_KUNENA_MYPROFILE_WEBSITE'); ?></span></a>
			<?php endif; ?>
		</div>
	</div>

	<?php if ($stats) : ?>
		<dl class="kb-profile-stats">
			<?php foreach ($stats as $label => $value) : ?>
				<div><dt><?php echo rtrim($label, ': '); ?></dt><dd class="kb-num"><?php echo is_int($value) ? $this->formatLargeNumber($value) : $value; ?></dd></div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<dl class="kb-profile-details">
		<?php foreach ($details as $label => $value) : ?>
			<div><dt><?php echo $label; ?></dt><dd><?php echo $value; ?></dd></div>
		<?php endforeach; ?>
	</dl>

	<?php echo $this->subLayout('User/Item/Social')->set('profile', $profile)->set('socials', $profile->socialButtons()); ?>

	<?php if ($personalText) : ?>
		<div class="kb-profile-about">
			<h2><?php echo Text::_('COM_KUNENA_MYPROFILE_ABOUTME'); ?></h2>
			<div><?php echo $personalText; ?></div>
		</div>
	<?php endif; ?>

	<?php if ($signature) : ?>
		<div class="kb-signature"><?php echo $signature; ?></div>
	<?php endif; ?>
</div>

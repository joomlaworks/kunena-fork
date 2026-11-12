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
use Joomla\CMS\Session\Session;

$template       = KunenaFactory::getTemplate();
$markAllReadUrl = KunenaForumCategoryHelper::get()->getMarkReadUrl();
$userStatus     = KunenaFactory::getConfig()->user_status;
$token          = '&' . Session::getFormToken() . '=1';
$pending        = KunenaTemplateBlackadder::pendingApproval();
$statuses       = array(
    0 => array('COM_KUNENA_ONLINE', 'circle-fill'),
    1 => array('COM_KUNENA_AWAY', 'moon'),
    2 => array('COM_KUNENA_BUSY', 'dash-circle-fill'),
    3 => array('COM_KUNENA_INVISIBLE', 'eye-slash'),
);
?>
<?php if (!empty($this->pm_link)) : ?>
	<a class="kb-btn kb-btn--ghost" href="<?php echo $this->pm_link; ?>" title="<?php echo $this->escape(strip_tags($this->inboxCount)); ?>">
		<?php echo $template->icon('envelope', strip_tags($this->inboxCount)); ?>
		<?php if ($this->inboxCountValue) : ?><span class="kb-badge"><?php echo (int) $this->inboxCountValue; ?></span><?php endif; ?>
	</a>
<?php endif; ?>

<details class="kb-dropdown">
	<summary class="kb-btn kb-btn--ghost" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_ACCOUNT'); ?>">
		<?php echo $template->avatar($this->me, 'sm'); ?>
		<span class="kb-account-name"><?php echo $this->escape($this->me->getName()); ?></span>
		<?php if ($pending) : ?><span class="kb-badge" title="<?php echo Text::_('KUNENA_BLACKADDER_AWAITING_APPROVAL'); ?>"><?php echo $pending; ?></span><?php endif; ?>
		<?php echo $template->icon('chevron-down'); ?>
	</summary>
	<div class="kb-dropdown-menu">
		<div class="kb-dropdown-head">
			<strong><?php echo $this->escape($this->me->getName()); ?></strong>
			<span class="kb-muted"><?php echo $template->icon('clock'); ?> <?php echo $this->me->getLastVisitDate()->toKunena('config_post_dateformat'); ?></span>
		</div>
		<hr>
		<a href="<?php echo $this->me->getURL(); ?>"><?php echo $template->icon('person-circle'); ?> <?php echo Text::_('KUNENA_BLACKADDER_PROFILE'); ?></a>
		<?php if ($this->profile_edit_url) : ?>
			<a href="<?php echo $this->profile_edit_url; ?>"><?php echo $template->icon('gear'); ?> <?php echo Text::_('COM_KUNENA_LOGOUTMENU_LABEL_PREFERENCES'); ?></a>
		<?php endif; ?>
		<?php if (!empty($this->pm_link)) : ?>
			<a href="<?php echo $this->pm_link; ?>"><?php echo $template->icon('envelope'); ?> <?php echo $this->inboxCount; ?></a>
		<?php endif; ?>
		<?php if ($pending) : ?>
			<a href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topics&layout=posts&mode=unapproved&userid=0'); ?>"><?php echo $template->icon('shield-check'); ?> <?php echo Text::_('KUNENA_BLACKADDER_AWAITING_APPROVAL'); ?> <span class="kb-badge"><?php echo $pending; ?></span></a>
		<?php endif; ?>
		<?php if (!empty($this->announcementsUrl)) : ?>
			<a href="<?php echo $this->announcementsUrl; ?>"><?php echo $template->icon('megaphone'); ?> <?php echo Text::_('COM_KUNENA_ANN_ANNOUNCEMENTS'); ?></a>
		<?php endif; ?>
		<?php if ($markAllReadUrl) : ?>
			<a href="<?php echo $markAllReadUrl; ?>"><?php echo $template->icon('check2-all'); ?> <?php echo Text::_('COM_KUNENA_MARK_ALL_READ'); ?></a>
		<?php endif; ?>
		<?php if ($userStatus) : ?>
			<hr>
			<div class="kb-dropdown-label"><?php echo Text::_('COM_KUNENA_STATUS'); ?></div>
			<?php foreach ($statuses as $value => $status) : ?>
				<a href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user&task=status&status=' . $value . $token); ?>"<?php echo (int) $this->me->status === $value ? ' aria-current="true"' : ''; ?>>
					<?php echo $template->icon($status[1]); ?> <?php echo Text::_($status[0]); ?>
				</a>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_logout'); ?>
		<?php if ($this->plglogin) : ?>
			<hr>
			<form method="post" action="<?php echo KunenaRoute::_('index.php?option=com_kunena'); ?>">
				<input type="hidden" name="view" value="user">
				<input type="hidden" name="task" value="logout">
				<?php echo HTMLHelper::_('form.token'); ?>
				<button type="submit"><?php echo $template->icon('box-arrow-right'); ?> <?php echo Text::_('COM_KUNENA_PROFILEBOX_LOGOUT'); ?></button>
			</form>
		<?php endif; ?>
		<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_logout_bottom'); ?>
	</div>
</details>

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

// Members: search and sort, then one card per member with the columns enabled in Kunena's configuration
$config    = $this->config;
$template  = KunenaFactory::getTemplate();
$ordering  = (string) $this->state->get('list.ordering');
$direction = strtolower((string) $this->state->get('list.direction')) === 'desc' ? 'desc' : 'asc';
$sorts     = array('id' => 'KUNENA_BLACKADDER_SORT_DEFAULT', $config->username ? 'username' : 'name' => $config->username ? 'COM_KUNENA_USERNAME' : 'COM_KUNENA_REALNAME');

foreach (array(
    'posts'         => array('userlist_posts', 'COM_KUNENA_USRL_POSTS'),
    'karma'         => array('userlist_karma', 'COM_KUNENA_USRL_KARMA'),
    'registerDate'  => array('userlist_joindate', 'COM_KUNENA_USRL_JOIN_DATE'),
    'lastvisitDate' => array('userlist_lastvisitdate', 'COM_KUNENA_USRL_LAST_LOGIN'),
    'uhits'         => array('userlist_userhits', 'COM_KUNENA_USRL_HITS'),
) as $column => $option) {
    if ($config->{$option[0]}) {
        $sorts[$column] = $option[1];
    }
}
?>
<div class="kb-title-row">
	<div class="kb-title">
		<h1><?php echo Text::_('COM_KUNENA_MEMBERS'); ?> <small><?php echo $this->formatLargeNumber((int) $this->pagination->total); ?></small></h1>
	</div>
	<form class="kb-toolbar-group" action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user&layout=list'); ?>" method="post" id="kuserlist-form">
		<?php echo HTMLHelper::_('form.token'); ?>
		<div class="kb-search kb-search--sm">
			<?php echo $template->icon('search'); ?>
			<input type="search" name="search" value="<?php echo $this->escape((string) $this->state->get('list.search')); ?>" placeholder="<?php echo Text::_('COM_KUNENA_USRL_SEARCH'); ?>" aria-label="<?php echo Text::_('COM_KUNENA_USRL_SEARCH'); ?>"/>
		</div>
		<select name="filter_order" data-kb-autosubmit aria-label="<?php echo Text::_('JGLOBAL_SORT_BY'); ?>">
			<?php foreach ($sorts as $column => $label) : ?>
				<option value="<?php echo $column; ?>"<?php echo $ordering === $column ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
			<?php endforeach; ?>
		</select>
		<select name="filter_order_Dir" data-kb-autosubmit aria-label="<?php echo Text::_('JGLOBAL_SORT_BY'); ?>">
			<option value="asc"<?php echo $direction === 'asc' ? ' selected' : ''; ?>>&uarr;</option>
			<option value="desc"<?php echo $direction === 'desc' ? ' selected' : ''; ?>>&darr;</option>
		</select>
		<noscript><button type="submit" class="kb-btn kb-btn--sm"><?php echo Text::_('COM_KUNENA_GO'); ?></button></noscript>
	</form>
</div>

<?php if (empty($this->users)) : ?>
	<p class="kb-empty"><?php echo Text::_('COM_KUNENA_NOUSERSFOUND'); ?></p>
<?php else : ?>
	<ul class="kb-members">
		<?php foreach ($this->users as $user) : ?>
			<li class="kb-member">
				<?php echo $config->userlist_avatar ? $user->getLink($template->avatar($user, 'lg'), null, 'nofollow', '', '') : ''; ?>
				<div class="kb-member-main">
					<div class="kb-member-name">
						<?php echo $user->getLink(null, null, 'nofollow', '', null); ?>
						<?php if ($config->userlist_online && $config->user_status) : ?>
							<?php echo $this->subLayout('User/Item/Status')->set('user', $user); ?>
						<?php endif; ?>
					</div>
					<div class="kb-topic-meta">
						<?php if ($config->userlist_posts) : ?><span><?php echo Text::sprintf('KUNENA_BLACKADDER_X_POSTS', $this->formatLargeNumber((int) $user->posts)); ?></span><?php endif; ?>
						<?php if ($config->userlist_karma) : ?><span><?php echo Text::_('COM_KUNENA_USRL_KARMA') . ' ' . (int) $user->karma; ?></span><?php endif; ?>
						<?php if ($config->userlist_joindate) : ?><span title="<?php echo $user->getRegisterDate()->toKunena('ago'); ?>"><?php echo Text::_('COM_KUNENA_USRL_JOIN_DATE') . ' ' . $user->getRegisterDate()->toKunena('date'); ?></span><?php endif; ?>
						<?php if ($config->userlist_lastvisitdate) : ?><span><?php echo Text::_('COM_KUNENA_USRL_LAST_LOGIN') . ' ' . $user->getLastVisitDate()->toKunena('ago'); ?></span><?php endif; ?>
						<?php if ($config->userlist_userhits) : ?><span><?php echo Text::_('COM_KUNENA_USRL_HITS') . ' ' . (int) $user->uhits; ?></span><?php endif; ?>
						<?php if ($config->userlist_email) : ?><span><?php echo $user->getEmailLink(); ?></span><?php endif; ?>
					</div>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination)->set('display', true); ?>
<?php endif; ?>

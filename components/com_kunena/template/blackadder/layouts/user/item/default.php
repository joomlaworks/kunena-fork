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

// A profile: the summary, then its sections (posts, subscriptions, favourites, thanks, moderation...) as tabs
$tabs = $this->getTabs();
?>
<div class="kb-stack kb-profile-page">
	<?php echo $this->subLayout('User/Item/Summary')
        ->set('profile', $this->profile)
        ->set('config', $this->config)
        ->set('candisplaymail', $this->candisplaymail); ?>

	<?php echo $this->subLayout('Widget/Module')->set('position', 'kunena_summary'); ?>

	<?php if ($tabs) : ?>
		<?php echo $this->subLayout('Widget/Tabs')->set('tabs', $tabs)->set('label', Text::_('KUNENA_BLACKADDER_PROFILE')); ?>
	<?php endif; ?>
</div>

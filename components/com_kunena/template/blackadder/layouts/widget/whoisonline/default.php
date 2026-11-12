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

$template = KunenaFactory::getTemplate();
$names    = array();

foreach ($this->onlineList as $user) {
    $names[] = $user->getLink(null, null, '');
}
?>
<div role="region" class="kb-footer kb-who" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_ONLINE_NOW'); ?>">
	<div class="kb-online">
		<span><?php echo $template->icon('people'); ?> <?php echo Text::sprintf('COM_KUNENA_VIEW_COMMON_WHO_TOTAL', $this->membersOnline); ?></span>
		<?php if ($names) : ?><span><?php echo implode(', ', $names); ?></span><?php endif; ?>
	</div>
	<?php if ($this->usersUrl) : ?>
		<a href="<?php echo $this->usersUrl; ?>"><?php echo Text::_('COM_KUNENA_MEMBERS'); ?></a>
	<?php endif; ?>
</div>

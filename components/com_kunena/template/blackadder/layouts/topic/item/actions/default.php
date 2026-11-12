<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Topic
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// Thread actions: reply (main button), subscribe and favourite as quiet actions, moderation in one menu
$template = KunenaFactory::getTemplate();
$buttons  = $this->topicButtons;
$moderate = array();

foreach (array('sticky', 'lock', 'moderate', 'undelete', 'delete', 'permdelete') as $name) {
    if ($buttons->get($name)) {
        $moderate[] = $buttons->get($name);
    }
}

if (!$buttons->get('reply') && !$buttons->get('subscribe') && !$buttons->get('favorite') && !$moderate) {
    return;
}
?>
<div class="kb-toolbar kb-topic-actions">
	<div class="kb-toolbar-group">
		<?php echo $buttons->get('subscribe'); ?>
		<?php echo $buttons->get('favorite'); ?>
		<?php if ($moderate) : ?>
			<details class="kb-dropdown kb-dropdown--left">
				<summary class="kb-action"><?php echo $template->icon('shield-check'); ?><span><?php echo Text::_('KUNENA_BLACKADDER_MODERATE'); ?></span><?php echo $template->icon('chevron-down'); ?></summary>
				<div class="kb-dropdown-menu"><?php echo implode('', $moderate); ?></div>
			</details>
		<?php endif; ?>
	</div>
	<div class="kb-toolbar-group"><?php echo $buttons->get('reply'); ?></div>
</div>

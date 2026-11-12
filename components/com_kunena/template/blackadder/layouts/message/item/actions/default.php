<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Message
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

/*
 * A post's actions: thank, copy link and reply in sight; quote, edit, delete, report and the moderation actions in
 * the "more" menu.
 */
$template = KunenaFactory::getTemplate();
$config   = KunenaConfig::getInstance();
$buttons  = $this->messageButtons;
$message  = $this->message;
$quick    = (int) $template->params->get('quick', 2);
$link     = $message->getUrl();

if (!empty($this->message_closed) && trim($this->message_closed) !== '') : ?>
	<span class="kb-muted"><?php echo $this->message_closed; ?></span>
	<?php return; ?>
<?php endif;

$more = array();

foreach (array('quote', 'edit') as $name) {
    if ($buttons->get($name)) {
        $more[] = $buttons->get($name);
    }
}

if ($buttons->get('delete') && $message->isAuthorised('delete')) {
    $more[] = $buttons->get('delete');
}

$me = KunenaUserHelper::getMyself();

if ($config->reportmsg && $me->exists()) {
    $more[] = '<a class="kb-action kbutton-report" rel="nofollow" href="'
        . KunenaRoute::_('index.php?option=com_kunena&view=topic&layout=report&catid=' . (int) $message->catid . '&id=' . (int) $message->thread . '&mesid=' . (int) $message->id)
        . '">' . $template->icon('flag') . '<span>' . Text::_('COM_KUNENA_REPORT') . '</span></a>';
}

$moderation = array();

foreach (array('moderate', 'publish', 'undelete', 'spam', 'permdelete') as $name) {
    if ($buttons->get($name)) {
        $moderation[] = $buttons->get($name);
    }
}
?>
<div class="kb-post-actions">
	<?php echo $buttons->get('thankyou'); ?>
	<?php echo $buttons->get('unthankyou'); ?>
	<button type="button" class="kb-action" data-kb-copy="<?php echo $this->escape($link); ?>" data-kb-copied="<?php echo Text::_('KUNENA_BLACKADDER_LINK_COPIED'); ?>" title="<?php echo Text::_('KUNENA_BLACKADDER_COPY_LINK'); ?>">
		<?php echo $template->icon('link-45deg'); ?><span class="kb-sr" data-kb-copy-label><?php echo Text::_('KUNENA_BLACKADDER_COPY_LINK'); ?></span>
	</button>
	<?php if ($more || $moderation) : ?>
		<details class="kb-dropdown">
			<summary class="kb-action" title="<?php echo Text::_('KUNENA_BLACKADDER_MORE_ACTIONS'); ?>"><?php echo $template->icon('three-dots', Text::_('KUNENA_BLACKADDER_MORE_ACTIONS')); ?></summary>
			<div class="kb-dropdown-menu">
				<?php echo implode('', $more); ?>
				<?php if ($moderation) : ?>
					<?php if ($more) : ?><hr><?php endif; ?>
					<div class="kb-dropdown-label"><?php echo Text::_('KUNENA_BLACKADDER_MODERATE'); ?></div>
					<?php echo implode('', $moderation); ?>
				<?php endif; ?>
			</div>
		</details>
	<?php endif; ?>
	<?php if ($this->quickreply && $quick && $message->isAuthorised('reply')) : ?>
		<a class="kb-action kbutton-reply" href="#kb-reply" data-kb-reply="<?php echo (int) $message->id; ?>" rel="nofollow"><?php echo $template->icon('reply'); ?><span><?php echo Text::_('COM_KUNENA_BUTTON_MESSAGE_REPLY'); ?></span></a>
	<?php else : ?>
		<?php echo $buttons->get('reply'); ?>
	<?php endif; ?>
</div>

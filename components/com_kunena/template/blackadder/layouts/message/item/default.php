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
 * A post (Discourse-like): avatar, name, role, time and permalink; the content; attachments, signature, "edited" note,
 * thanks; the actions in the footer.
 */
$message     = $this->message;
$author      = $this->profile;
$template    = KunenaFactory::getTemplate();
$config      = KunenaConfig::getInstance();
$isReply     = $message->id != $this->topic->first_post_id;
$signature   = $author->getSignature();
$attachments = $message->getAttachments();
$attachs     = $message->getNbAttachments();
$state       = $message->getState();
$numLink     = $config->ordering_system == 'mesid' ? $this->location : $message->replynum;
$role        = $author->exists() ? $author->getType($this->category->id, true) : 'guest';
$isAi        = $author->exists() && $author->userid == KunenaTemplateBlackadder::baldrickId();
$classes     = 'kb-post kb-post--' . $state;

if ($isAi) {
    $classes .= ' kb-post--ai';
}

$avatar = $template->avatar($author, 'lg', $message->name);

// Attachments placed in the text ([attachment=ID]) are not listed again under it
preg_match_all('/\[attachment=(\d+)\]/i', (string) $message->message, $matches);
$inlineIds = array_map('intval', $matches[1]);
?>
<div role="article" class="<?php echo $classes; ?>" id="<?php echo (int) $message->id; ?>">
	<div class="kb-post-avatar">
		<?php echo $author->exists() ? $author->getLink($avatar, null, 'nofollow', '', '', $this->category->id) : $avatar; ?>
	</div>

	<div class="kb-post-head">
		<span class="kb-post-author"><?php echo $author->getLink($this->escape($author->getName($message->name)), null, 'nofollow', '', null, $this->category->id); ?></span>
		<?php if ($isAi) : ?>
			<span class="kb-chip kb-chip--role"><?php echo $template->icon('robot') . ' ' . Text::_('KUNENA_BLACKADDER_AI'); ?></span>
		<?php elseif (in_array($role, array('admin', 'localadmin', 'globalmod', 'moderator'), true)) : ?>
			<span class="kb-chip kb-chip--role"><?php echo Text::_($author->getType($this->category->id)); ?></span>
		<?php endif; ?>
		<?php if ($this->topic->first_post_userid && $this->topic->first_post_userid == $message->userid && $isReply) : ?>
			<span class="kb-chip"><?php echo Text::_('COM_KUNENA_TOPIC_AUTHOR'); ?></span>
		<?php endif; ?>
		<span class="kb-post-when">
			<?php if (!empty($this->ipLink) && !empty($message->ip)) : ?>
				<span class="kb-post-ip"><?php echo $template->icon('geo') . ' ' . $this->ipLink; ?></span>
			<?php endif; ?>
			<?php echo $message->getTime()->toSpan('config_post_dateformat', 'config_post_dateformat_hover'); ?>
			<a href="#<?php echo (int) $message->id; ?>" rel="canonical">#<?php echo (int) $numLink; ?></a>
		</span>
	</div>

	<?php if ($state !== 'published') : ?>
		<div class="kb-post-state">
			<span class="kb-chip kb-chip--solid"><?php echo Text::_($state === 'unapproved' ? 'KUNENA_BLACKADDER_STATE_UNAPPROVED' : 'KUNENA_BLACKADDER_STATE_DELETED'); ?></span>
		</div>
	<?php endif; ?>

	<div class="kb-post-body">
		<div class="kmsg">
			<?php
            if (!$this->me->userid && $isReply && $this->config->teaser) {
                echo Text::_('COM_KUNENA_TEASER_TEXT');
            } else {
                echo $message->displayField('message');
            }
?>
		</div>

		<?php
        $listed = array_filter($attachments, function ($attachment) use ($inlineIds) {
            return !$attachment->inline && !in_array((int) $attachment->id, $inlineIds, true);
        });
?>
		<?php if (!empty($listed)) : ?>
			<ul class="kb-attachments">
				<?php foreach ($listed as $attachment) : ?>
					<?php if ($attachment->isAudio()) : ?>
						<li><?php echo $attachment->getLayout()->render('audio'); ?></li>
					<?php elseif ($attachment->isVideo()) : ?>
						<li><?php echo $attachment->getLayout()->render('video'); ?></li>
					<?php else : ?>
						<li><?php echo $attachment->getLayout()->render('thumbnail'); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php elseif (empty($attachments) && $attachs->total > 0 && !$this->me->exists()) : ?>
			<?php
            if ($attachs->image > 0 && !$this->config->showimgforguest) {
                echo KunenaLayout::factory('BBCode/Image')->set('title', Text::_($attachs->image > 1 ? 'COM_KUNENA_SHOWIMGFORGUEST_HIDEIMG_MULTIPLES' : 'COM_KUNENA_SHOWIMGFORGUEST_HIDEIMG_SIMPLE'))->setLayout('unauthorised');
            }

		    if ($attachs->file > 0 && !$this->config->showfileforguest) {
		        echo KunenaLayout::factory('BBCode/Image')->set('title', Text::_($attachs->file > 1 ? 'COM_KUNENA_SHOWIMGFORGUEST_HIDEFILE_MULTIPLES' : 'COM_KUNENA_SHOWIMGFORGUEST_HIDEFILE_SIMPLE'))->setLayout('unauthorised');
		    }
		    ?>
		<?php endif; ?>

		<?php if ($signature) : ?>
			<div class="kb-signature"><?php echo $signature; ?></div>
		<?php endif; ?>

		<?php if ($message->modified_by && $this->config->editmarkup) : ?>
			<p class="kb-post-edited">
				<?php echo Text::sprintf('KUNENA_BLACKADDER_EDITED', $message->modified_time ? KunenaDate::getInstance($message->modified_time)->toSpan('config_post_dateformat', 'config_post_dateformat_hover') : '', $message->getModifier()->getLink(null, null, 'nofollow', '', '', $this->category->id)); ?>
				<?php if ($message->modified_reason) : ?>
					&middot; <?php echo Text::_('COM_KUNENA_REASON') . ': ' . $this->escape($message->modified_reason); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>

	<div class="kb-post-foot">
		<div class="kb-post-thanks">
			<?php if (!empty($this->thankyou)) :
			    $list = array();

			    foreach ($this->thankyou as $userid => $thank) {
			        $list[] = $thank . (!empty($this->thankyou_delete[$userid])
			            ? ' <a class="kb-muted" title="' . Text::_('COM_KUNENA_BUTTON_THANKYOU_REMOVE_LONG') . '" href="' . $this->thankyou_delete[$userid] . '">' . $template->icon('x-lg', Text::_('COM_KUNENA_BUTTON_THANKYOU_REMOVE_LONG')) . '</a>'
			            : '');
			    }

			    $more = $this->more_thankyou ? ' ' . Text::sprintf('COM_KUNENA_THANKYOU_MORE_USERS', $this->more_thankyou) : '';
			    ?>
				<?php echo $template->icon('heart-fill'); ?>
				<span><?php echo Text::sprintf('KUNENA_BLACKADDER_THANKED_BY', implode(', ', $list) . $more); ?></span>
			<?php endif; ?>
		</div>
		<?php echo isset($this->actionsHtml) ? $this->actionsHtml : ''; ?>
	</div>
</div>

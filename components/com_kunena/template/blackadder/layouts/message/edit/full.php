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


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/*
 * The reply box at the bottom of a thread. A post's "Reply" action points here (data-kb-reply sets the parent post).
 * Guests who may not post see a login/register line instead. Mentions (@) and emojis (:) use Kunena's atwho plugin,
 * set up by blackadder.js.
 */
$message  = $this->message;
$template = KunenaFactory::getTemplate();
$me       = KunenaUserHelper::getMyself();
$topic    = isset($this->topic) ? $this->topic : $message->getTopic();
$category = isset($this->category) ? $this->category : $message->getCategory();
$config   = KunenaFactory::getConfig();

if (!$message->isAuthorised('reply')) {
    if (!$me->exists() && !$topic->locked) {
        $login = KunenaLogin::getInstance();
        $reg   = $login ? $login->getRegistrationURL() : '';
        $here  = '#kb-login';
        echo '<p class="kb-guest-reply" id="kb-reply">'
            . ($reg ? Text::sprintf('KUNENA_BLACKADDER_LOGIN_TO_REPLY', $here, $this->escape($reg)) : Text::sprintf('KUNENA_BLACKADDER_LOGIN_TO_REPLY_NOREG', $here))
            . '</p>';
    }

    return;
}

$captcha = KunenaCaptcha::isRequired($me) ? KunenaCaptcha::display('kunena_captcha_' . (int) $message->id) : '';
$subscribed = $config->subscriptionschecked == 1 && $me->canSubscribe != 0 || $config->subscriptionschecked == 0 && $me->canSubscribe == 1 || $category->getSubscribed($me->userid);
$fullEditor = KunenaRoute::_('index.php?option=com_kunena&view=topic&layout=reply&catid=' . (int) $category->id . '&id=' . (int) $topic->id . '&mesid=' . (int) $topic->last_post_id);
?>
<div class="kb-reply" id="kb-reply">
	<?php echo $template->avatar($me, 'lg'); ?>
	<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic'); ?>" method="post" enctype="multipart/form-data" name="postform" id="postform">
		<input type="hidden" name="task" value="post"/>
		<input type="hidden" name="parentid" value="<?php echo (int) $topic->last_post_id; ?>" data-kb-parent data-kb-default="<?php echo (int) $topic->last_post_id; ?>"/>
		<input type="hidden" name="catid" value="<?php echo (int) $category->id; ?>"/>
		<input type="hidden" name="subject" value="<?php echo $this->escape($topic->subject); ?>"/>
		<?php echo HTMLHelper::_('form.token'); ?>
		<?php if ($me->exists()) : ?>
			<input type="hidden" id="kurl_users" name="kurl_users" value="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user&layout=listmention&format=raw'); ?>"/>
		<?php endif; ?>
		<input type="hidden" id="kurl_emojis" name="kurl_emojis" value="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic&layout=listemoji&format=raw'); ?>"/>
		<input type="hidden" id="kemojis_allowed" name="kemojis_allowed" value="<?php echo $config->disemoticons ? 0 : 1; ?>"/>

		<p class="kb-reply-to" data-kb-reply-to hidden>
			<span data-kb-reply-to-label data-kb-template="<?php echo $this->escape(Text::_('KUNENA_BLACKADDER_REPLY_TO')); ?>"></span>
			<button type="button" class="kb-action" data-kb-reply-reset><?php echo Text::_('KUNENA_BLACKADDER_CANCEL_REPLY_TO'); ?></button>
		</p>

		<?php if (!$me->exists()) : ?>
			<div class="kb-reply-row">
				<label class="kb-field"><?php echo Text::_('COM_KUNENA_GEN_NAME'); ?>
					<input type="text" name="authorname" maxlength="35" required autocomplete="name"/>
				</label>
				<?php if ($config->askemail) : ?>
					<label class="kb-field"><?php echo Text::_('COM_KUNENA_TOPIC_EDIT_PLACEHOLDER_EMAIL'); ?>
						<input type="email" name="email" maxlength="100" required autocomplete="email"/>
					</label>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<label class="kb-sr" for="editor"><?php echo Text::_('KUNENA_BLACKADDER_REPLY_TO_TOPIC'); ?></label>
		<textarea id="editor" class="qreply" name="message" rows="5" required placeholder="<?php echo Text::_('KUNENA_BLACKADDER_REPLY_PLACEHOLDER'); ?>"></textarea>

		<?php if ($captcha !== '') : ?>
			<div class="kb-captcha"><?php echo $captcha; ?></div>
		<?php endif; ?>

		<div class="kb-reply-actions">
			<div>
				<?php if ($topic->isAuthorised('subscribe')) : ?>
					<label class="kb-check"><input type="checkbox" name="subscribeMe" value="1"<?php echo $subscribed ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_POST_NOTIFIED'); ?></label>
				<?php endif; ?>
				<?php if ($me->exists() && $category->allow_anonymous) : ?>
					<label class="kb-check"><input type="checkbox" name="anonymous" value="1"<?php echo $category->post_anonymous ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_POST_AS_ANONYMOUS_DESC'); ?></label>
				<?php endif; ?>
			</div>
			<div>
				<a class="kb-action" href="<?php echo $fullEditor; ?>" rel="nofollow" data-kb-full-editor><?php echo $template->icon('pencil-square'); ?><span><?php echo Text::_('KUNENA_BLACKADDER_FULL_EDITOR'); ?></span></a>
				<button type="submit" class="kb-btn kb-btn--primary"><?php echo Text::_('KUNENA_BLACKADDER_POST_REPLY'); ?></button>
			</div>
		</div>
	</form>
</div>

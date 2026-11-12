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


use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/*
 * The posting form (new topic, reply, edit): one card with the category, the guest's name, the subject, a BBCode
 * editor (toolbar, Write/Preview), attachments uploaded in the background through Kunena's upload task, an optional
 * poll, and the options. Everything is plain HTML that works without JavaScript; blackadder.js adds the toolbar,
 * preview, uploads, poll options, the category's template text and mentions/emojis.
 */
HTMLHelper::_('behavior.keepalive');

$template  = KunenaFactory::getTemplate();
$config    = $this->config;
$me        = isset($this->me) ? $this->me : KunenaUserHelper::getMyself();
$message   = $this->message;
$topic     = $this->topic;
$category  = $this->category;
$editing   = $message->exists();
$firstPost = $editing ? $topic->first_post_id == $message->id : !$message->parent;
$token     = Session::getFormToken();

// Categories which allow anonymous posts (=> checked by default?) and polls, for the category selector
$anonymousMap = array();
$pollMap      = array();

if (isset($this->selectcatlist)) {
    foreach (KunenaForumCategoryHelper::getCategories() as $item) {
        if ($item->isSection()) {
            continue;
        }

        if ($item->allow_anonymous) {
            $anonymousMap[$item->id] = (int) $item->post_anonymous;
        }

        if ($config->pollenabled && $item->allow_polls) {
            $pollMap[$item->id] = 1;
        }
    }
}

// Poll: on a topic's first post, in categories with polls; when editing, only if the controller allowed it
$poll     = $editing ? (isset($this->poll) ? $this->poll : null) : $message->getTopic()->getPoll();
$showPoll = $config->pollenabled && $firstPost && $poll
    && (isset($this->selectcatlist) ? !empty($pollMap) : $topic->getCategory()->allow_polls);

$canAttach   = !empty($this->allowedExtensions) && !empty($this->UserCanPostImage);
$attachments = $editing ? $message->getAttachments() : array();

// Mentions and emojis in the editor (Kunena's jQuery plugins)
$this->addScript('jquery.caret.js');
$this->addScript('jquery.atwho.js');

$tools = array(
    array('b', 'type-bold', 'COM_KUNENA_EDITOR_BOLD'),
    array('i', 'type-italic', 'COM_KUNENA_EDITOR_ITALIC'),
    array('u', 'type-underline', 'COM_KUNENA_EDITOR_UNDERL'),
    array('strike', 'type-strikethrough', 'COM_KUNENA_EDITOR_STRIKE'),
    array('url', 'link-45deg', 'COM_KUNENA_EDITOR_LINK'),
    array('img', 'image', 'COM_KUNENA_EDITOR_IMAGELINK'),
    array('quote', 'quote', 'COM_KUNENA_EDITOR_QUOTE'),
    array('code', 'code-slash', 'COM_KUNENA_EDITOR_CODE'),
    array('ul', 'list-ul', 'COM_KUNENA_EDITOR_ULIST'),
    array('ol', 'list-ol', 'COM_KUNENA_EDITOR_OLIST'),
    array('spoiler', 'eye-slash', 'COM_KUNENA_EDITOR_SPOILER'),
);

if (empty($this->UserCanPostImage)) {
    unset($tools[5]);
}

$cancel = $topic->exists() ? $topic->getUrl($category, false) : $category->getUrl(null, false);
?>
<div class="kb-stack kb-editpage">
	<div class="kb-title">
		<h1><?php echo $this->escape($this->headerText); ?></h1>
	</div>

	<form action="<?php echo KunenaRoute::_('index.php?option=com_kunena'); ?>" method="post" enctype="multipart/form-data"
	      class="kb-form" id="postform" name="postform" data-kb-postform
	      data-kb-preview-url="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic&layout=edit&format=raw', false); ?>"
	      data-kb-template-url="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic&layout=categorytemplatetext&format=raw', false); ?>"
	      data-kb-upload-url="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic&task=upload&format=json&' . $token . '=1', false); ?>"
	      data-kb-inline-url="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic&task=setinline&format=json&' . $token . '=1', false); ?>"
	      data-kb-max-files="<?php echo (int) $config->attachment_limit; ?>"
	      data-kb-anonymous="<?php echo $this->escape(json_encode((object) $anonymousMap)); ?>"
	      data-kb-polls="<?php echo $this->escape(json_encode((object) $pollMap)); ?>"
	      data-kb-empty="<?php echo Text::_('KUNENA_BLACKADDER_NOTHING_TO_PREVIEW'); ?>"
	      data-kb-insert-label="<?php echo Text::_('COM_KUNENA_EDITOR_INSERT'); ?>"
	      data-kb-remove-label="<?php echo Text::_('KUNENA_BLACKADDER_REMOVE'); ?>"
	      data-kb-too-many="<?php echo $this->escape(Text::_('KUNENA_BLACKADDER_TOO_MANY_FILES')); ?>">
		<input type="hidden" name="view" value="topic"/>
		<?php if ($editing) : ?>
			<input type="hidden" name="task" value="edit"/>
			<input type="hidden" name="mesid" value="<?php echo (int) $message->id; ?>"/>
		<?php else : ?>
			<input type="hidden" name="task" value="post"/>
			<input type="hidden" name="parentid" value="<?php echo (int) $message->parent; ?>"/>
		<?php endif; ?>
		<?php if (!isset($this->selectcatlist)) : ?>
			<input type="hidden" name="catid" value="<?php echo (int) $message->catid; ?>" data-kb-catid/>
		<?php endif; ?>
		<?php if ($category->id && $category->id != $message->catid) : ?>
			<input type="hidden" name="return" value="<?php echo (int) $category->id; ?>"/>
		<?php endif; ?>
		<?php if (!$config->allow_change_subject) : ?>
			<input type="hidden" name="subject" value="<?php echo $this->escape($message->subject); ?>"/>
		<?php endif; ?>
		<?php if ($me->exists()) : ?>
			<input type="hidden" id="kurl_users" value="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user&layout=listmention&format=raw'); ?>"/>
		<?php endif; ?>
		<input type="hidden" id="kurl_emojis" value="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topic&layout=listemoji&format=raw'); ?>"/>
		<input type="hidden" id="kemojis_allowed" value="<?php echo $config->disemoticons ? 0 : 1; ?>"/>
		<?php echo HTMLHelper::_('form.token'); ?>

		<div class="kb-card">
			<?php if (isset($this->selectcatlist)) : ?>
				<label class="kb-field"><?php echo Text::_('COM_KUNENA_CATEGORY'); ?>
					<?php echo $this->selectcatlist; ?>
				</label>
			<?php endif; ?>

			<?php if (!$me->userid) : ?>
				<p class="kb-alert"><?php echo Text::_('COM_KUNENA_GEN_INFO_GUEST_CANNOT_EDIT_DELETE_MESSAGE'); ?></p>
				<div class="kb-form-row">
					<label class="kb-field"><?php echo Text::_('COM_KUNENA_GEN_NAME'); ?>
						<input type="text" name="authorname" maxlength="35" required autocomplete="name" value="<?php echo $this->escape($message->name); ?>"/>
					</label>
					<?php if ($config->askemail) : ?>
						<label class="kb-field"><?php echo Text::_('COM_KUNENA_GEN_EMAIL'); ?>
							<input type="email" name="email" maxlength="100" required autocomplete="email" value="<?php echo $this->escape((string) $message->email); ?>"/>
							<small class="kb-muted"><?php echo Text::_($config->showemail == '0' ? 'COM_KUNENA_POST_EMAIL_NEVER' : 'COM_KUNENA_POST_EMAIL_REGISTERED'); ?></small>
						</label>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ($config->allow_change_subject || !$topic->exists() || $me->isModerator($message->getCategory())) : ?>
				<label class="kb-field"><?php echo Text::_('COM_KUNENA_GEN_SUBJECT'); ?>
					<input type="text" name="subject" id="subject" required
					       maxlength="<?php echo (int) $template->params->get('SubjectLengthMessage', 100); ?>"
					       placeholder="<?php echo Text::_('COM_KUNENA_TOPIC_EDIT_PLACEHOLDER_SUBJECT'); ?>"
					       value="<?php echo $this->escape($message->subject); ?>"/>
				</label>
			<?php endif; ?>

			<div class="kb-editor" data-kb-editor>
				<div class="kb-editor-bar" role="toolbar" aria-label="<?php echo Text::_('COM_KUNENA_MESSAGE'); ?>">
					<div class="kb-editor-tabs" role="tablist">
						<button type="button" class="kb-action" role="tab" aria-selected="true" data-kb-tab="write"><?php echo Text::_('COM_KUNENA_MESSAGE'); ?></button>
						<button type="button" class="kb-action" role="tab" aria-selected="false" data-kb-tab="preview"><?php echo Text::_('COM_KUNENA_PREVIEW'); ?></button>
					</div>
					<div class="kb-editor-tools" data-kb-tools hidden>
						<?php foreach ($tools as $tool) : ?>
							<button type="button" class="kb-action" data-kb-bbcode="<?php echo $tool[0]; ?>" title="<?php echo Text::_($tool[2]); ?>"><?php echo $template->icon($tool[1], Text::_($tool[2])); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<label class="kb-sr" for="editor"><?php echo Text::_('COM_KUNENA_MESSAGE'); ?></label>
				<textarea name="message" id="editor" rows="12" required data-kb-message><?php echo $this->escape($message->message); ?></textarea>
				<div class="kb-editor-preview kb-post-body" data-kb-preview hidden></div>
			</div>

			<?php if ($canAttach) : ?>
				<div class="kb-attach" data-kb-attach>
					<div class="kb-attach-head">
						<label class="kb-btn kb-btn--sm kb-upload">
							<?php echo $template->icon('paperclip'); ?><span><?php echo Text::_('COM_KUNENA_UPLOADED_LABEL_ADD_FILES_BUTTON'); ?></span>
							<input type="file" name="kattachment" multiple data-kb-upload/>
						</label>
						<small class="kb-muted">
							<?php echo Text::_('COM_KUNENA_FILE_EXTENSIONS_ALLOWED') . ': ' . $this->escape(implode(', ', $this->allowedExtensions)); ?>
							&middot; <?php echo Text::_('COM_KUNENA_UPLOAD_MAX_FILES_WEIGHT') . ': ' . round($config->filesize / 1024, 1) . ' ' . Text::_('COM_KUNENA_UPLOAD_ATTACHMENT_FILE_WEIGHT_MB'); ?>
							&middot; <?php echo Text::_('COM_KUNENA_UPLOAD_MAX_IMAGES_WEIGHT') . ': ' . round($config->imagesize / 1024, 1) . ' ' . Text::_('COM_KUNENA_UPLOAD_ATTACHMENT_FILE_WEIGHT_MB'); ?>
						</small>
					</div>
					<ul class="kb-files" data-kb-files>
						<?php foreach ($attachments as $attachment) : ?>
							<li class="kb-file" data-kb-file="<?php echo (int) $attachment->id; ?>" data-kb-name="<?php echo $this->escape($attachment->getFilename(false)); ?>">
								<input type="hidden" name="attachments[<?php echo (int) $attachment->id; ?>]" value="1"/>
								<?php if ($attachment->isImage()) : ?>
									<img src="<?php echo $attachment->getUrl(true); ?>" alt="" width="48" height="48" loading="lazy"/>
								<?php else : ?>
									<?php echo $template->icon('file-earmark'); ?>
								<?php endif; ?>
								<span class="kb-file-name"><?php echo $this->escape($attachment->getFilename(false)); ?></span>
								<button type="button" class="kb-action" data-kb-insert><?php echo Text::_('COM_KUNENA_EDITOR_INSERT'); ?></button>
								<label class="kb-check"><input type="checkbox" name="attachment[<?php echo (int) $attachment->id; ?>]" value="1" checked/> <?php echo Text::_('KUNENA_BLACKADDER_KEEP'); ?></label>
							</li>
						<?php endforeach; ?>
					</ul>
					<p class="kb-muted kb-attach-note" data-kb-upload-error hidden></p>
				</div>
			<?php endif; ?>

			<?php if ($showPoll) :
			    $options = $poll->exists() ? $poll->getOptions() : array();
			    ?>
				<details class="kb-poll-form" data-kb-poll<?php echo $poll->exists() ? ' open' : ''; ?><?php echo isset($this->selectcatlist) ? '' : ' data-kb-poll-fixed'; ?>>
					<summary class="kb-action"><?php echo $template->icon('bar-chart') . ' ' . Text::_('COM_KUNENA_EDITOR_POLL_SETTINGS'); ?></summary>
					<div class="kb-poll-fields">
						<div class="kb-form-row">
							<label class="kb-field"><?php echo Text::_('COM_KUNENA_POLL_TITLE'); ?>
								<input type="text" name="poll_title" maxlength="100" value="<?php echo $this->escape($poll->title); ?>"/>
							</label>
							<label class="kb-field"><?php echo Text::_('COM_KUNENA_POLL_TIME_TO_LIVE'); ?>
								<input type="date" name="poll_time_to_live" value="<?php echo !empty($poll->polltimetolive) && substr((string) $poll->polltimetolive, 0, 4) > '1901' ? $this->escape(substr((string) $poll->polltimetolive, 0, 10)) : ''; ?>"/>
							</label>
						</div>
						<ol class="kb-poll-options" data-kb-poll-options data-kb-max="<?php echo (int) $config->pollnboptions; ?>">
							<?php if ($options) : ?>
								<?php foreach ($options as $option) : ?>
									<li><input type="text" name="polloptionsID[<?php echo (int) $option->id; ?>]" maxlength="100" value="<?php echo $this->escape($option->text); ?>" aria-label="<?php echo Text::_('COM_KUNENA_POLL_OPTION_NAME'); ?>"/></li>
								<?php endforeach; ?>
							<?php else : ?>
								<li><input type="text" name="polloptionsID[new1]" maxlength="100" aria-label="<?php echo Text::_('COM_KUNENA_POLL_OPTION_NAME'); ?>"/></li>
								<li><input type="text" name="polloptionsID[new2]" maxlength="100" aria-label="<?php echo Text::_('COM_KUNENA_POLL_OPTION_NAME'); ?>"/></li>
							<?php endif; ?>
						</ol>
						<button type="button" class="kb-action" data-kb-poll-add><?php echo $template->icon('plus-circle') . ' ' . Text::_('COM_KUNENA_POLL_ADD_POLL_OPTION'); ?></button>
					</div>
				</details>
			<?php endif; ?>

			<?php if ($editing && $config->editmarkup) : ?>
				<label class="kb-field"><?php echo Text::_('COM_KUNENA_EDITING_REASON'); ?>
					<input type="text" name="modified_reason" maxlength="200" placeholder="<?php echo Text::_('COM_KUNENA_EDITING_ENTER_REASON'); ?>" value="<?php echo $this->escape((string) $message->modified_reason); ?>"/>
				</label>
			<?php endif; ?>

			<div class="kb-form-options">
				<?php if ($me->userid) : ?>
					<label class="kb-check" data-kb-anonymous-box<?php echo !$category->allow_anonymous && !isset($this->selectcatlist) ? ' hidden' : ''; ?><?php echo isset($this->selectcatlist) && !isset($anonymousMap[$message->catid]) ? ' hidden' : ''; ?>>
						<input type="checkbox" name="anonymous" value="1"<?php echo !empty($this->post_anonymous) ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_POST_AS_ANONYMOUS_DESC'); ?>
					</label>
				<?php endif; ?>
				<?php if (!empty($this->canSubscribe)) : ?>
					<label class="kb-check"><input type="checkbox" name="subscribeMe" value="1"<?php echo !empty($this->subscriptionschecked) ? ' checked' : ''; ?>/> <?php echo Text::_('COM_KUNENA_POST_NOTIFIED'); ?></label>
				<?php endif; ?>
			</div>

			<?php if (!empty($this->captchaEnabled)) : ?>
				<div class="kb-captcha"><?php echo $this->captchaDisplay; ?></div>
			<?php endif; ?>
		</div>

		<div class="kb-toolbar">
			<a class="kb-btn kb-btn--ghost" href="<?php echo $this->escape($cancel); ?>"><?php echo Text::_('COM_KUNENA_CANCEL'); ?></a>
			<button type="submit" class="kb-btn kb-btn--primary" id="form_submit_button"><?php echo Text::_('COM_KUNENA_SUBMIT'); ?></button>
		</div>
	</form>

	<?php if ($config->showhistory && $topic->exists()) {
	    echo $this->subRequest('Topic/Form/History', new \Joomla\Input\Input(array('id' => $topic->id)));
	} ?>
</div>

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
use Joomla\CMS\Session\Session;

// Avatar: upload one (sent at once through Kunena's upload task) or pick one from the gallery
$template = KunenaFactory::getTemplate();
?>
<div class="kb-card">
	<div class="kb-avatar-edit">
		<span data-kb-avatar-preview><?php echo $template->avatar($this->profile, 'xl'); ?></span>
		<?php if ($this->config->allowavatarupload) : ?>
			<div class="kb-field" data-kb-avatar-upload
			     data-kb-url="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=user&task=upload&format=json&' . Session::getFormToken() . '=1', false); ?>"
			     data-kb-userid="<?php echo (int) $this->user->id; ?>"
			     data-kb-done="<?php echo $this->escape(Text::_('KUNENA_BLACKADDER_AVATAR_SAVED')); ?>">
				<span><?php echo Text::_('COM_KUNENA_PROFILE_AVATAR_UPLOAD'); ?></span>
				<label class="kb-btn kb-btn--sm kb-upload">
					<?php echo $template->icon('upload'); ?><span><?php echo Text::_('COM_KUNENA_UPLOADED_LABEL_ADD_AVATAR_BUTTON'); ?></span>
					<input type="file" accept="image/*" data-kb-avatar-file/>
				</label>
				<small class="kb-muted" data-kb-avatar-status><?php echo $this->escape(strtoupper(str_replace(',', ', ', (string) $this->config->avatartypes))); ?></small>
			</div>
		<?php endif; ?>
	</div>

	<?php if ($this->config->allowavatargallery && ($this->galleryOptions || $this->galleryImages)) : ?>
		<div class="kb-field">
			<span><?php echo Text::_('COM_KUNENA_PROFILE_AVATAR_GALLERY'); ?></span>
			<?php if ($this->galleryImages) : ?>
				<ul class="kb-avatar-gallery">
					<?php foreach ($this->galleryImages as $image) : ?>
						<li>
							<label>
								<input type="radio" name="avatar_gallery" value="<?php echo $this->escape('gallery/' . $image); ?>"/>
								<img src="<?php echo $this->escape($this->galleryUri . '/' . $image); ?>" alt="" width="64" height="64" loading="lazy"/>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

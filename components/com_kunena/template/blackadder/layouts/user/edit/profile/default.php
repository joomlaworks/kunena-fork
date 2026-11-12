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

// Profile: about me, birthdate, location, gender, website, social networks, signature
$profile   = $this->profile;
$birthdate = (string) $profile->birthdate;
$birthdate = preg_match('/^(\d{4})-\d{2}-\d{2}/', $birthdate, $m) && $m[1] > 1901 ? substr($birthdate, 0, 10) : '';
?>
<div class="kb-card">
	<?php if ($this->config->personal) : ?>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_PERSONALTEXT'); ?>
			<input type="text" name="personaltext" maxlength="<?php echo (int) $this->config->maxpersotext; ?>" value="<?php echo $this->escape((string) $profile->personalText); ?>"/>
			<small class="kb-muted"><?php echo Text::_('COM_KUNENA_MYPROFILE_PERSONALTEXT_DESC'); ?></small>
		</label>
	<?php endif; ?>
	<div class="kb-search-grid">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_BIRTHDATE'); ?>
			<input type="date" name="birthdate" value="<?php echo $this->escape($birthdate); ?>"/>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_LOCATION'); ?>
			<input type="text" name="location" value="<?php echo $this->escape((string) $profile->location); ?>"/>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_GENDER'); ?>
			<select name="gender">
				<?php foreach (array(0 => 'COM_KUNENA_MYPROFILE_GENDER_UNKNOWN', 1 => 'COM_KUNENA_MYPROFILE_GENDER_MALE', 2 => 'COM_KUNENA_MYPROFILE_GENDER_FEMALE') as $value => $label) : ?>
					<option value="<?php echo $value; ?>"<?php echo (int) $profile->gender === $value ? ' selected' : ''; ?>><?php echo Text::_($label); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	</div>
	<div class="kb-form-row">
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_WEBSITE_NAME'); ?>
			<input type="text" name="websitename" maxlength="25" value="<?php echo $this->escape((string) $profile->websitename); ?>"/>
		</label>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_WEBSITE_URL'); ?>
			<input type="url" name="websiteurl" value="<?php echo $this->escape((string) $profile->getWebsiteURL()); ?>"/>
		</label>
	</div>
	<?php if ($this->config->signature) : ?>
		<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_SIGNATURE'); ?>
			<textarea name="signature" rows="4" maxlength="<?php echo (int) $this->config->maxsig; ?>"><?php echo $this->escape((string) $profile->signature); ?></textarea>
			<small class="kb-muted"><?php echo Text::_('COM_KUNENA_MYPROFILE_SIGNATURE_DESC'); ?></small>
		</label>
	<?php endif; ?>
</div>

<?php if ($this->config->social) : ?>
	<details class="kb-card kb-search-options">
		<summary class="kb-action"><?php echo Text::_('KUNENA_BLACKADDER_SOCIAL'); ?></summary>
		<div class="kb-search-grid">
			<?php foreach ($this->social as $key => $social) : ?>
				<label class="kb-field"><?php echo Text::_('COM_KUNENA_MYPROFILE_' . $key); ?>
					<input type="text" name="<?php echo $this->escape($key); ?>" value="<?php echo $this->escape((string) $profile->$key); ?>"/>
				</label>
			<?php endforeach; ?>
		</div>
	</details>
<?php endif; ?>

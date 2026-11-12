<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Attachment
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// An attachment in the post's attachment list: image thumbnail or file icon, with its name
$attachment = $this->attachment;

if (!$attachment->getPath()) {
    return;
}

$config   = KunenaConfig::getInstance();
$template = KunenaFactory::getTemplate();
$url      = $attachment->getUrl(false, false, true);
$name     = $attachment->getShortName($config->attach_start, $config->attach_end);
$isImage  = $attachment->isImage();
?>
<a class="kb-attachment" href="<?php echo $url; ?>" title="<?php echo $attachment->getFilename(); ?>"<?php echo $isImage && $config->lightbox ? ' data-kb-lightbox' : ''; ?>>
	<?php if ($isImage) : ?>
		<img src="<?php echo $url; ?>" alt="" loading="lazy" width="<?php echo (int) $config->thumbwidth; ?>" height="<?php echo (int) $config->thumbheight; ?>"/>
	<?php else : ?>
		<?php echo $template->icon('file-earmark'); ?>
	<?php endif; ?>
	<span><?php echo $name; ?></span>
	<small class="kb-muted kb-num"><?php echo number_format($attachment->size / 1024, 0, '', ','); ?> KB</small>
</a>

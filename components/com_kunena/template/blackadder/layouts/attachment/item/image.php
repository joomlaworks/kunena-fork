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


// An image in a post (inline [attachment] or the attachment list): opens full size in the lightbox when enabled
$attachment = $this->attachment;

if (!$attachment->isImage() || !$attachment->getPath()) {
    return;
}

$config = KunenaConfig::getInstance();
$size   = @getimagesize($attachment->getPath());
$url    = $attachment->getUrl();
$name   = $attachment->getShortName($config->attach_start, $config->attach_end);
?>
<a class="kb-attachment-image" href="<?php echo $url; ?>" title="<?php echo $name; ?>"<?php echo $config->lightbox ? ' data-kb-lightbox' : ''; ?>>
	<img src="<?php echo $url; ?>" alt="<?php echo $attachment->getFilename(); ?>" loading="lazy"<?php echo $size ? ' width="' . (int) $size[0] . '" height="' . (int) $size[1] . '"' : ''; ?>/>
</a>

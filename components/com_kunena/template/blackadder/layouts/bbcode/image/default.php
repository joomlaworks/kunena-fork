<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.BBCode
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// An [img] in a post: opens full size in Blackadder's lightbox (Kunena's "lightbox" option, on by default)
$config  = KunenaConfig::getInstance();
$url     = $this->url;
$alt     = $this->alt ?: rawurldecode(basename((string) parse_url((string) $url, PHP_URL_PATH)));
$canLink = isset($this->canLink) ? $this->canLink : true;
$style   = 'max-height:' . (int) $config->imageheight . 'px;' . ($this->size ? 'max-width:' . (int) $this->size . 'px;' : '');
$img     = '<img src="' . $this->escape($url) . '" alt="' . $this->escape((string) $alt) . '" style="' . $style . '" loading="lazy"/>';
?>
<span class="kmsgimage">
	<?php if ($canLink) : ?>
		<a class="kb-attachment-image" href="<?php echo $this->escape($url); ?>" title="<?php echo $this->escape((string) $alt); ?>"<?php echo $config->lightbox ? ' data-kb-lightbox' : ''; ?>><?php echo $img; ?></a>
	<?php else : ?>
		<?php echo $img; ?>
	<?php endif; ?>
</span>

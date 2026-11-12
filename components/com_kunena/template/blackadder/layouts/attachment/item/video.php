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


use Joomla\CMS\Language\Text;

// A video file: the browser's player and a download link
$attachment = $this->attachment;

if (!$attachment->isVideo()) {
    return;
}

$location = $attachment->getUrl();
?>
<div class="kb-media">
	<video src="<?php echo $location; ?>" controls preload="metadata"></video>
	<div class="kb-media-caption"><?php echo $attachment->getShortName(); ?>
		<a href="<?php echo $location; ?>" download><?php echo KunenaFactory::getTemplate()->icon('download', Text::_('KUNENA_BLACKADDER_DOWNLOAD')); ?></a>
	</div>
</div>

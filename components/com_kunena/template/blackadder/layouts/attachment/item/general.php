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


// A file which is not an image, audio or video
$attachment = $this->attachment;
$template   = KunenaFactory::getTemplate();
?>
<a class="kb-attachment" href="<?php echo $attachment->getUrl(); ?>" title="<?php echo $attachment->getFilename(); ?>">
	<?php echo $template->icon('file-earmark'); ?><span><?php echo $this->escape($attachment->getShortName()); ?></span>
</a>

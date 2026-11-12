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


use Joomla\CMS\Language\Text;

// [quote] / [quote="name" post=ID]: who wrote it (linked to the post when known), then the quoted text. Names are
// escaped without encoding twice what the parser already encoded.
$name = htmlspecialchars((string) $this->username, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
?>
<blockquote class="kb-quote">
	<?php if (!empty($this->msglink)) : ?>
		<span class="kb-cite"><a href="<?php echo $this->escape($this->msglink); ?>"><?php echo $name . ' ' . Text::_('COM_KUNENA_POST_WROTE'); ?> <?php echo KunenaFactory::getTemplate()->icon('arrow-up'); ?></a></span>
	<?php elseif (!empty($this->username)) : ?>
		<span class="kb-cite"><?php echo $name; ?></span>
	<?php endif; ?>
	<div><?php echo $this->content; ?></div>
</blockquote>

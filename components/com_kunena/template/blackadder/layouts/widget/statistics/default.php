<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Widget
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// Forum statistics: one centred line
$template = KunenaFactory::getTemplate();
$line     = Text::sprintf('KUNENA_BLACKADDER_STATS_LINE', number_format((int) $this->topicCount), number_format((int) $this->messageCount), number_format((int) $this->memberCount));
?>
<div role="region" class="kb-footer kb-stats" aria-label="<?php echo Text::_('COM_KUNENA_STATISTICS'); ?>">
	<div class="kb-footer-links">
		<span class="kb-num"><?php echo $template->icon('graph-up'); ?> <?php echo $line; ?></span>
		<?php if ($this->latestMemberLink) : ?>
			<span><?php echo Text::sprintf('KUNENA_BLACKADDER_NEWEST_MEMBER', $this->latestMemberLink); ?></span>
		<?php endif; ?>
		<?php if ($this->statisticsUrl) : ?>
			<a href="<?php echo $this->statisticsUrl; ?>"><?php echo Text::_('COM_KUNENA_STATISTICS'); ?></a>
		<?php endif; ?>
	</div>
</div>

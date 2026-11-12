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

// The RSS link (the page generation time is no longer shown)
$rss = $this->getRSS();

if ($rss === null) {
    return;
}
?>
<div class="kb-footer kb-footer--meta">
	<div class="kb-footer-links">
		<?php // The RSS link with its label (Kunena's link holds the icon only)
        $href = preg_match('/href="([^"]+)"/', $rss, $match) ? $match[1] : ''; ?>
		<a rel="alternate" type="application/rss+xml" href="<?php echo $href; ?>" title="<?php echo \Joomla\CMS\Language\Text::_('COM_KUNENA_CATEGORIES_LABEL_GETRSS'); ?>"><?php echo KunenaFactory::getTemplate()->icon('rss') . ' ' . \Joomla\CMS\Language\Text::_('KUNENA_BLACKADDER_RSS'); ?></a>
	</div>
</div>

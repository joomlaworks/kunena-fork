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

$pathway = $this->breadcrumb->getPathway();

if (!$pathway) {
    return;
}

$template = KunenaFactory::getTemplate();
$last     = count($pathway) - 1;
?>
<ol class="kb-crumbs" aria-label="<?php echo Text::_('KUNENA_BLACKADDER_BREADCRUMB'); ?>" itemscope itemtype="https://schema.org/BreadcrumbList">
	<?php foreach ($pathway as $i => $item) : ?>
		<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
			<?php if ($i > 0) : ?><?php echo $template->icon('chevron-right'); ?><?php endif; ?>
			<?php if ($i === $last) : ?>
				<span itemprop="name" aria-current="page"><?php echo $this->escape($item->name); ?></span>
			<?php else : ?>
				<a itemprop="item" href="<?php echo $item->link; ?>"><span itemprop="name"><?php echo $this->escape($item->name); ?></span></a>
			<?php endif; ?>
			<meta itemprop="position" content="<?php echo $i + 1; ?>">
		</li>
	<?php endforeach; ?>
</ol>

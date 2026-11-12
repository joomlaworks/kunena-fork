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


use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/*
 * The category index (Discourse's "categories" page): sections as groups, one row per category with its description,
 * subcategories, counts and latest thread. Inside a category (view=category), the same layout shows the category's title
 * and its subcategories above the thread list.
 *
 * Every row and the category page carry kb-cat--<alias>: style them in your template, e.g.
 *   .kb-cat--announcements { --kb-cat: #b45309; }
 */
$template = KunenaFactory::getTemplate();
$config   = $template->params;
$kconfig  = KunenaConfig::getInstance();
$input    = Factory::getApplication()->input;
$isIndex  = $input->getCmd('view') !== 'category' || $input->getCmd('layout', 'default') === 'list';
$mmm      = 0;

$catClass = function ($category) {
    return 'kb-cat--' . preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $category->alias));
};

$count = function ($number) {
    return number_format((int) $number);
};

if ($config->get('displayModule')) {
    echo $this->subLayout('Widget/Module')->set('position', 'kunena_index_top');
}

if ($isIndex) : ?>
	<div class="kb-title">
		<h1><?php echo Text::_('COM_KUNENA_CATEGORIES'); ?></h1>
	</div>
<?php endif;

foreach ($this->sections as $section) :
    $children = !empty($this->categories[$section->id]) ? $this->categories[$section->id] : array();

    if ($config->get('displayModule')) {
        echo $this->subLayout('Widget/Module')->set('position', 'kunena_section_top_' . ++$mmm);
    }

    if (!$isIndex) : ?>
		<?php // Inside a category: its title, then its subcategories (if any)?>
		<div class="kb-title <?php echo $catClass($section); ?>">
			<h1><?php echo $this->escape($section->name); ?></h1>
			<?php if (!empty($section->description)) : ?>
				<div class="kb-title-desc"><?php echo $section->displayField('description'); ?></div>
			<?php endif; ?>
		</div>
		<?php if (!$children) : ?>
			<?php continue; ?>
		<?php endif; ?>
	<?php endif; ?>

	<div class="kb-section <?php echo $catClass($section); ?>" id="section<?php echo (int) $section->id; ?>">
		<?php if ($isIndex) : ?>
			<div class="kb-section-head">
				<h2><?php echo $this->getCategoryLink($section, $this->escape($section->name), '', null, true, false); ?></h2>
				<?php if (!empty($section->description)) : ?>
					<div class="kb-section-desc"><?php echo $section->displayField('description'); ?></div>
				<?php endif; ?>
			</div>
		<?php else : ?>
			<div class="kb-section-head"><h2><?php echo Text::_('KUNENA_BLACKADDER_SUBCATEGORIES'); ?></h2></div>
		<?php endif; ?>

		<?php if ($section->isSection() && !$children && empty($this->more[$section->id])) : ?>
			<p class="kb-empty"><?php echo Text::_('COM_KUNENA_GEN_NOFORUMS'); ?></p>
		<?php else : ?>
			<div class="kb-table kb-cats">
				<div class="kb-table-head" aria-hidden="true">
					<span><?php echo Text::_('KUNENA_BLACKADDER_CATEGORY'); ?></span>
					<span><?php echo Text::_('KUNENA_BLACKADDER_TOPICS'); ?></span>
					<span><?php echo Text::_('KUNENA_BLACKADDER_LATEST'); ?></span>
				</div>

				<?php foreach ($children as $category) :
				    $last = $category->getLastTopic();
				    $new = $category->getNewCount();
				    ?>
					<div class="kb-row <?php echo $catClass($category) . $this->escape($category->class_sfx); ?>" id="category<?php echo (int) $category->id; ?>">
						<div class="kb-cat-main">
							<div class="kb-cat-name">
								<?php echo $this->getCategoryLink($category, $this->escape($category->name), '', null, true, false); ?>
								<?php if ($new > 0) : ?><span class="kb-new"><?php echo Text::sprintf('KUNENA_BLACKADDER_UNREAD', (int) $new); ?></span><?php endif; ?>
								<?php if ($category->locked) : ?><span class="kb-muted" title="<?php echo Text::_('COM_KUNENA_LOCKED_CATEGORY'); ?>"><?php echo $template->icon('lock-fill', Text::_('COM_KUNENA_LOCKED_CATEGORY')); ?></span><?php endif; ?>
								<?php if ($category->review) : ?><span class="kb-muted" title="<?php echo Text::_('COM_KUNENA_GEN_MODERATED'); ?>"><?php echo $template->icon('shield-check', Text::_('COM_KUNENA_GEN_MODERATED')); ?></span><?php endif; ?>
							</div>
							<?php if (!empty($category->description)) : ?>
								<div class="kb-cat-desc"><?php echo $category->displayField('description'); ?></div>
							<?php endif; ?>
							<?php if (!empty($this->categories[$category->id]) || !empty($this->more[$category->id])) : ?>
								<div class="kb-subcats">
									<?php foreach ($this->categories[$category->id] ?? array() as $subcategory) : ?>
										<?php echo $this->getCategoryLink($subcategory, $this->escape($subcategory->name), '', 'kb-chip kb-chip--cat ' . $catClass($subcategory), true, false); ?>
									<?php endforeach; ?>
									<?php if (!empty($this->more[$category->id])) : ?>
										<?php echo $this->getCategoryLink($category, Text::sprintf('COM_KUNENA_X_HIDDEN', (int) $this->more[$category->id]), '', 'kb-chip', true, false); ?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							<?php if ($category->getModerators() && $kconfig->listcat_show_moderators) : ?>
								<?php
				                $mods = array();

							    foreach ($category->getModerators() as $moderator) {
							        $mods[] = KunenaFactory::getUser($moderator)->getLink(null, null, '');
							    }
							    ?>
								<div class="kb-cat-desc"><?php echo Text::_('COM_KUNENA_MODERATORS'); ?>: <?php echo implode(', ', $mods); ?></div>
							<?php endif; ?>
							<?php if (!empty($this->pending[$category->id])) : ?>
								<a class="kb-chip kb-chip--solid" rel="nofollow" href="<?php echo KunenaRoute::_('index.php?option=com_kunena&view=topics&layout=posts&mode=unapproved&userid=0&catid=' . (int) $category->id); ?>">
									<?php echo (int) $this->pending[$category->id] . ' ' . Text::_('COM_KUNENA_SHOWCAT_PENDING'); ?>
								</a>
							<?php endif; ?>
						</div>

						<div class="kb-cat-count kb-num">
							<b><?php echo $count($category->getTopics()); ?></b>
							<?php echo Text::sprintf('KUNENA_BLACKADDER_X_POSTS', $count($category->getPosts())); ?>
						</div>

						<?php if ($last->exists()) :
						    $author = $last->getLastPostAuthor();
						    ?>
							<div class="kb-latest">
								<?php echo $template->avatar($author, 'sm', $last->last_post_guest_name); ?>
								<div>
									<?php echo $this->getLastPostLink($category, null, '', 'kb-latest-title', 80, false, true); ?>
									<span class="kb-latest-meta"><?php echo Text::sprintf('KUNENA_BLACKADDER_BY_X', $this->escape($author->getName($last->last_post_guest_name)), $last->getLastPostTime()->toKunena('config_post_dateformat')); ?></span>
								</div>
							</div>
						<?php else : ?>
							<div class="kb-latest kb-muted"><?php echo Text::_('COM_KUNENA_X_TOPICS_0'); ?></div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>

				<?php if (!empty($this->more[$section->id])) : ?>
					<div class="kb-row">
						<div><?php echo $this->getCategoryLink($section, Text::_('COM_KUNENA_SEE_ALL_SUBJECTS')); ?>
							<span class="kb-muted">(<?php echo Text::sprintf('COM_KUNENA_X_HIDDEN', (int) $this->more[$section->id]); ?>)</span></div>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ($config->get('displayModule')) {
	    echo $this->subLayout('Widget/Module')->set('position', 'kunena_section_' . ++$mmm);
	} ?>
<?php endforeach;

if ($config->get('displayModule')) {
    echo $this->subLayout('Widget/Module')->set('position', 'kunena_index_bottom');
}

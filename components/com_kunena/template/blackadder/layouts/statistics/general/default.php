<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Statistics
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// Forum statistics: the totals, then the top lists (topics, posters, profiles, thanks...) with bars
$members = $this->userlistUrl ? '<a href="' . $this->userlistUrl . '">' . $this->formatLargeNumber((int) $this->memberCount) . '</a>' : $this->formatLargeNumber((int) $this->memberCount);
$totals  = array(
    'COM_KUNENA_STAT_TOTAL_USERS'            => $members,
    'COM_KUNENA_STAT_TOTAL_MESSAGES'         => $this->formatLargeNumber((int) $this->messageCount),
    'COM_KUNENA_STAT_TOTAL_SUBJECTS'         => $this->formatLargeNumber((int) $this->topicCount),
    'COM_KUNENA_STAT_TOTAL_SECTIONS'         => (int) $this->sectionCount,
    'COM_KUNENA_STAT_TOTAL_CATEGORIES'       => (int) $this->categoryCount,
    'COM_KUNENA_STAT_TODAY_OPEN_THREAD'      => (int) $this->todayTopicCount,
    'COM_KUNENA_STAT_YESTERDAY_OPEN_THREAD'  => (int) $this->yesterdayTopicCount,
    'COM_KUNENA_STAT_TODAY_TOTAL_ANSWER'     => (int) $this->todayReplyCount,
    'COM_KUNENA_STAT_YESTERDAY_TOTAL_ANSWER' => (int) $this->yesterdayReplyCount,
);
?>
<div class="kb-title"><h1><?php echo Text::_('COM_KUNENA_STATISTICS'); ?></h1></div>

<?php if ($this->config->showgenstats) : ?>
	<dl class="kb-profile-stats kb-stats-grid">
		<?php foreach ($totals as $label => $value) : ?>
			<div><dt><?php echo Text::_($label); ?></dt><dd class="kb-num"><?php echo $value; ?></dd></div>
		<?php endforeach; ?>
	</dl>
	<?php if ($this->latestMemberLink) : ?>
		<p class="kb-muted"><?php echo Text::sprintf('KUNENA_BLACKADDER_NEWEST_MEMBER', $this->latestMemberLink); ?></p>
	<?php endif; ?>
<?php endif; ?>

<div class="kb-top-lists">
	<?php foreach ($this->top as $top) : ?>
		<div class="kb-card">
			<h2 class="kb-subhead"><?php echo $top[0]->title; ?></h2>
			<ol class="kb-poll-results">
				<?php foreach ($top as $item) : ?>
					<li>
						<div class="kb-poll-line"><span><?php echo $item->link; ?></span><span class="kb-muted kb-num"><?php echo $item->count; ?></span></div>
						<div class="kb-poll-bar"><span style="width:<?php echo (float) $item->percent; ?>%"></span></div>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endforeach; ?>
</div>

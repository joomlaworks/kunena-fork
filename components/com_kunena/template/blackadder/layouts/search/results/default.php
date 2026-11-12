<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Search
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

// Search results: posts, with the range and pagination at the bottom
if (empty($this->results)) {
    if ($this->state->get('searchwords') !== null && $this->state->get('searchwords') !== '' || $this->state->get('query.searchuser')) {
        echo '<p class="kb-empty">' . ($this->error ?: Text::_('COM_KUNENA_SEARCH_ERR_NOPOSTS')) . '</p>';
    }

    return;
}

$start = $this->pagination->limitstart + 1;
$stop  = $this->pagination->limitstart + count($this->results);
?>
<div class="kb-title-row">
	<div class="kb-title">
		<h2><?php echo Text::_('COM_KUNENA_SEARCH_RESULTS'); ?> <small class="kb-muted"><?php echo Text::sprintf('COM_KUNENA_FORUM_SEARCHRESULTS', $start . '–' . $stop, (int) $this->pagination->total); ?></small></h2>
	</div>
</div>
<?php if ($this->error) : ?>
	<p class="kb-alert"><?php echo $this->error; ?></p>
<?php endif; ?>
<div class="kb-posts kb-results">
	<?php foreach ($this->results as $message) {
	    echo $this->subLayout('Search/Results/Row')->set('message', $message);
	} ?>
</div>
<?php echo $this->subLayout('Widget/Pagination/List')->set('pagination', $this->pagination)->set('display', true); ?>

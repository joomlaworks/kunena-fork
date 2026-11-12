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


/*
 * Tabs: $this->tabs (name => object with title, content, active). Plain HTML shows every section with its title;
 * blackadder.js turns the titles into a tab bar.
 */
$active = null;

foreach ($this->tabs as $name => $tab) {
    if (!empty($tab->active)) {
        $active = $name;
        break;
    }
}

$active = $active ?: key($this->tabs);
?>
<div class="kb-tabs" data-kb-tabs>
	<?php foreach ($this->tabs as $name => $tab) :
	    $id = 'kb-tab-' . preg_replace('/[^a-z0-9_-]/i', '', (string) $name);
	    ?>
		<div class="kb-tab" id="<?php echo $id; ?>" data-kb-tab-panel<?php echo $name === $active ? ' data-kb-active' : ''; ?>>
			<h2 class="kb-tab-title" data-kb-tab-title><?php echo $tab->title; ?></h2>
			<div class="kb-tab-body"><?php echo $tab->content; ?></div>
		</div>
	<?php endforeach; ?>
</div>

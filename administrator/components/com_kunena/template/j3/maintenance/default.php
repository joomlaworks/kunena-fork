<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator.Template
 * @subpackage    Maintenance
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// Every maintenance job on one page: name, description, its own fields, a Run button and its result below
Factory::getDocument()->addScript(Uri::root(true) . '/administrator/components/com_kunena/template/j3/maintenance/maintenance.js');

$escape = function ($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$url   = 'index.php?option=com_kunena&view=maintenance&task=run&format=json';
$token = HTMLHelper::_('form.token');

/**
 * One job: a form posting to the maintenance task.
 *
 * @param   string  $job      Job name
 * @param   string  $title    Name (translated)
 * @param   string  $desc     Description (translated HTML)
 * @param   string  $fields   The job's fields (HTML)
 * @param   string  $confirm  Confirmation question (translated), or '' for none
 * @param   string  $button   Button label (translated), or '' for "Run"
 * @param   string  $class    Button class
 */
$job = function ($job, $title, $desc, $fields = '', $confirm = '', $button = '', $class = 'btn-primary') use ($escape, $url, $token) {
    ?>
	<form class="kunena-maintenance-job" id="kunena-job-<?php echo $escape($job); ?>" action="<?php echo $escape($url); ?>" method="post"
		  data-job="<?php echo $escape($job); ?>" data-working="<?php echo $escape(Text::_('COM_KUNENA_MAINTENANCE_WORKING')); ?>"<?php echo $confirm !== '' ? ' data-confirm="' . $escape($confirm) . '"' : ''; ?>>
		<div class="kunena-maintenance-main">
			<h4><?php echo $title; ?></h4>
			<p class="kunena-maintenance-desc"><?php echo $desc; ?></p>
			<?php if ($fields !== '') : ?>
				<div class="kunena-maintenance-fields"><?php echo $fields; ?></div>
			<?php endif; ?>
			<div class="kunena-maintenance-result" role="status" aria-live="polite" hidden></div>
		</div>
		<div class="kunena-maintenance-action">
			<input type="hidden" name="job" value="<?php echo $escape($job); ?>"/>
			<?php echo $token; ?>
			<button type="submit" class="btn <?php echo $escape($class); ?>"><?php echo $button !== '' ? $button : Text::_('COM_KUNENA_MAINTENANCE_RUN'); ?></button>
		</div>
	</form>
	<?php
};
// A field: its label above it (a div: Kunena's administrator CSS makes every label inline)
$field = function ($id, $label, $control, $class = '') use ($escape) {
    return '<div class="kunena-maintenance-field' . ($class !== '' ? ' ' . $class : '') . '"><label for="' . $escape($id) . '">' . $label . '</label>' . $control . '</div>';
};
$days = function ($id, $name, $value, $label) use ($escape, $field) {
    return $field($id, $label, '<span class="kunena-unit-field"><input type="number" class="ksm-field" id="' . $escape($id) . '" name="'
        . $escape($name) . '" value="' . (int) $value . '" min="0" step="1"/> ' . Text::_('COM_KUNENA_A_PRUNE_DAYS') . '</span>');
};
$select = function ($id, $name, array $options, $selected, $label) use ($escape, $field) {
    $html = '<select id="' . $escape($id) . '" name="' . $escape($name) . '" class="input-large">';

    foreach ($options as $value => $text) {
        $html .= '<option value="' . $escape($value) . '"' . ((string) $value === (string) $selected ? ' selected="selected"' : '') . '>' . $escape($text) . '</option>';
    }

    return $field($id, $label, $html . '</select>');
};
?>
<div id="kunena" class="admin override">
	<div id="j-sidebar-container" class="span2">
		<div id="sidebar">
			<div class="sidebar-nav"><?php include KPATH_ADMIN . '/template/j3/common/menu.php'; ?></div>
		</div>
	</div>
	<div id="j-main-container" class="span10 kunena-maintenance">
		<p class="lead"><?php echo Text::_('COM_KUNENA_MAINTENANCE_DESC'); ?></p>

		<?php if (!$this->canRun) : ?>
			<div class="alert alert-error"><?php echo Text::_('COM_KUNENA_MAINTENANCE_NOT_ALLOWED'); ?></div>
		<?php else : ?>
			<h3><?php echo Text::_('COM_KUNENA_A_RECOUNT'); ?></h3>
			<?php
            $job('recount_topics', Text::_('COM_KUNENA_A_RECOUNT_TOPICS'), Text::_('COM_KUNENA_A_RECOUNT_TOPICS_DESC'));
		    $job('recount_usertopics', Text::_('COM_KUNENA_A_RECOUNT_USERTOPICS'), Text::_('COM_KUNENA_A_RECOUNT_USERTOPICS_DESC'));
		    $job('recount_categories', Text::_('COM_KUNENA_A_RECOUNT_CATEGORIES'), Text::_('COM_KUNENA_A_RECOUNT_CATEGORIES_DESC'));
		    $job('recount_users', Text::_('COM_KUNENA_A_RECOUNT_USERS'), Text::_('COM_KUNENA_A_RECOUNT_USERS_DESC'));
		    $job('recount_polls', Text::_('COM_KUNENA_MAINTENANCE_RECOUNT_POLLS'), Text::_('COM_KUNENA_MAINTENANCE_RECOUNT_POLLS_DESC'));
		    ?>

			<h3><?php echo Text::_('COM_KUNENA_SYNC_USERS'); ?></h3>
			<?php
		    $job('users_add', Text::_('COM_KUNENA_SYNC_USERS_ADD'), Text::_('COM_KUNENA_SYNC_USERS_ADD_DESC'));
		    $job('users_remove', Text::_('COM_KUNENA_SYNC_USERS_DEL'), Text::_('COM_KUNENA_SYNC_USERS_DEL_DESC'), '', Text::_('COM_KUNENA_MAINTENANCE_CONFIRM'));
		    $job('users_rename', Text::_('COM_KUNENA_SYNC_USERS_RENAME'), Text::_('COM_KUNENA_SYNC_USERS_RENAME_DESC'));
		    $job('users_lifetime', Text::_('COM_KUNENA_SYNC_USERS_DELLIFE'), Text::_('COM_KUNENA_MAINTENANCE_LIFETIME_DESC'), '', Text::_('COM_KUNENA_MAINTENANCE_LIFETIME_CONFIRM'), '', 'btn-danger');
		    ?>

			<h3><?php echo Text::_('COM_KUNENA_CFG_SECTION_USERNAMES'); ?></h3>
			<?php
		    $job(
		        'names_preview',
		        Text::_('COM_KUNENA_NORMALIZENAMES_PREVIEW_TITLE'),
		        Text::sprintf($this->namesOn ? 'COM_KUNENA_NORMALIZENAMES_PREVIEW_DESC' : 'COM_KUNENA_NORMALIZENAMES_DISABLED', $escape('index.php?option=com_kunena&view=config')),
		        '',
		        '',
		        Text::_('COM_KUNENA_NORMALIZENAMES_PREVIEW_BUTTON'),
		        'btn-default'
		    );
		    $job(
		        'names_normalize',
		        Text::_('COM_KUNENA_NORMALIZENAMES'),
		        Text::sprintf('COM_KUNENA_NORMALIZENAMES_DESC', $escape('index.php?option=com_kunena&view=config')) . ' <strong>' . Text::_('COM_KUNENA_NORMALIZENAMES_WARNING') . '</strong>',
		        '',
		        Text::_('COM_KUNENA_NORMALIZENAMES_CONFIRM'),
		        '',
		        'btn-danger'
		    );
		    ?>

			<h3><?php echo Text::_('COM_KUNENA_MAINTENANCE_POSTS'); ?></h3>
			<?php
		    $job(
		        'purge_re',
		        Text::_('COM_KUNENA_A_PURGE_RE_STATEMENTS'),
		        Text::_('COM_KUNENA_MAINTENANCE_PURGE_RE_DESC'),
		        $field('kunena-purge-prefix', Text::_('COM_KUNENA_MAINTENANCE_PURGE_RE_PREFIX'), '<input type="text" id="kunena-purge-prefix" name="prefix" value="Re:" class="ksm-field kunena-maintenance-wide" maxlength="255" required="required"/>', 'kunena-maintenance-field--block'),
		        Text::_('COM_KUNENA_MAINTENANCE_CONFIRM')
		    );
		    $job(
		        'ip_posts',
		        Text::_('COM_KUNENA_LEGEND_CLEANUP_IP'),
		        Text::_('COM_KUNENA_MAINTENANCE_IP_POSTS_DESC'),
		        $days('kunena-ip-days', 'days', 365, Text::_('COM_KUNENA_CLEANUP_IP_LEGEND_FROMDAYS')),
		        Text::_('COM_KUNENA_MAINTENANCE_CONFIRM')
		    );
		    $job('ip_users', Text::_('COM_KUNENA_DELETE_USERS_IP'), Text::_('COM_KUNENA_MAINTENANCE_IP_USERS_DESC'), '', Text::_('COM_KUNENA_MAINTENANCE_CONFIRM'));
		    $job(
		        'prune',
		        Text::_('COM_KUNENA_A_PRUNE'),
		        Text::_('COM_KUNENA_A_PRUNE_DESC'),
		        $field('categories', Text::_('COM_KUNENA_A_PRUNE_NAME'), $this->categories, 'kunena-maintenance-field--block')
		        . $days('kunena-prune-days', 'days', 30, Text::_('COM_KUNENA_A_PRUNE_NOPOSTS'))
		        . $select(
		            'kunena-prune-only',
		            'only',
		            array(
		                'normal'     => Text::_('COM_KUNENA_A_PRUNE_NORMAL'),
		                'all'        => Text::_('COM_KUNENA_A_PRUNE_ALL'),
		                'locked'     => Text::_('COM_KUNENA_A_PRUNE_LOCKED'),
		                'unanswered' => Text::_('COM_KUNENA_A_PRUNE_UNANSWERED'),
		                'answered'   => Text::_('COM_KUNENA_A_PRUNE_ANSWERED'),
		                'unapproved' => Text::_('COM_KUNENA_A_PRUNE_UNAPPROVED'),
		                'deleted'    => Text::_('COM_KUNENA_A_PRUNE_DELETED'),
		                'shadow'     => Text::_('COM_KUNENA_A_PRUNE_SHADOW'),
		            ),
		            'normal',
		            Text::_('COM_KUNENA_A_PRUNE_CONTROL_OPTIONS')
		        )
		        . $select('kunena-prune-sticky', 'keepsticky', array('1' => Text::_('JYES'), '0' => Text::_('JNO')), '1', Text::_('COM_KUNENA_A_PRUNE_KEEP_STICKY'))
		        . $select('kunena-prune-delete', 'delete', array('0' => Text::_('COM_KUNENA_MAINTENANCE_PRUNE_TRASH'), '1' => Text::_('COM_KUNENA_MAINTENANCE_PRUNE_DELETE')), '0', Text::_('COM_KUNENA_MAINTENANCE_PRUNE_ACTION')),
		        Text::_('COM_KUNENA_MAINTENANCE_PRUNE_CONFIRM'),
		        '',
		        'btn-danger'
		    );
		    ?>

			<h3 id="emptytrash"><?php echo Text::_('COM_KUNENA_MAINTENANCE_TRASH'); ?></h3>
			<?php
		    $job(
		        'empty_trash',
		        Text::_('COM_KUNENA_EMPTYTRASH'),
		        Text::_('COM_KUNENA_EMPTYTRASH_DESC') . ' ' . Text::sprintf('COM_KUNENA_MAINTENANCE_TRASH_COUNTS', (int) $this->trashCounts['topics'], (int) $this->trashCounts['messages'])
		        . ' <strong>' . Text::_('COM_KUNENA_EMPTYTRASH_WARNING') . '</strong>',
		        '',
		        Text::_('COM_KUNENA_EMPTYTRASH_CONFIRM'),
		        '',
		        'btn-danger'
		    );
		    ?>
		<?php endif; ?>
	</div>

	<div class="kunena-version small">
		<?php echo KunenaAdminVersion::getLongVersionHTML(); ?>
	</div>
</div>

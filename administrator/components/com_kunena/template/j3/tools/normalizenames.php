<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator.Template
 * @subpackage    Tools
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

Text::script('COM_KUNENA_NORMALIZENAMES_CONFIRM');
Text::script('COM_KUNENA_AJAX_TASK_WORKING');
Text::script('COM_KUNENA_AJAX_ERROR');
Text::script('COM_KUNENA_AJAXMODAL_ERROR_TIMEOUT_BODY');
Text::script('COM_KUNENA_AJAXMODAL_ERROR_UNKNOWN_BODY');

HTMLHelper::_('jquery.framework');
Factory::getDocument()->addScript(Uri::root(true) . '/administrator/components/com_kunena/template/j3/tools/ajaxtask.js');

$counts  = $this->namesCounts;
$preview = $this->namesPreview;
$url     = 'index.php?option=com_kunena&view=tools&task=normalizenames&format=json&' . Session::getFormToken() . '=1';
$config  = 'index.php?option=com_kunena&view=config#tab_members';
$escape  = function ($text) {
    return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
?>

<div id="kunena" class="admin override">
    <div id="j-sidebar-container" class="span2">
        <div id="sidebar">
            <div class="sidebar-nav"><?php include KPATH_ADMIN . '/template/j3/common/menu.php'; ?></div>
        </div>
    </div>
    <div id="j-main-container" class="span10">
        <form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=tools') ?>"
              method="post" id="adminForm" name="adminForm">
            <input type="hidden" name="task" value=""/>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <fieldset>
            <legend><?php echo Text::_('COM_KUNENA_NORMALIZENAMES'); ?></legend>
            <p><?php echo Text::sprintf('COM_KUNENA_NORMALIZENAMES_DESC', $escape($config)); ?></p>

            <?php if (!$this->namesEnabled) : ?>
                <div class="alert alert-info"><?php echo Text::sprintf('COM_KUNENA_NORMALIZENAMES_DISABLED', $escape($config)); ?></div>
            <?php else : ?>
                <table class="table table-bordered table-striped" id="kunena-normalizenames-counts">
                    <tr>
                        <td><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_ACCOUNTS'); ?></td>
                        <td><strong data-kunena-ajax-zero><?php echo (int) $counts['accounts']; ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_USERNAMES'); ?></td>
                        <td><strong data-kunena-ajax-zero><?php echo (int) $counts['usernames']; ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_NAMES'); ?></td>
                        <td><strong data-kunena-ajax-zero><?php echo (int) $counts['names']; ?></strong></td>
                    </tr>
                </table>

                <?php if (!$this->canNormalize) : ?>
                    <div class="alert alert-error"><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_NOT_ALLOWED'); ?></div>
                <?php elseif (!$counts['accounts']) : ?>
                    <div class="alert alert-success"><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_NONE'); ?></div>
                <?php else : ?>
                    <div class="alert alert-warning"><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_WARNING'); ?></div>
                    <button type="button" class="btn btn-primary btn-large kunena-ajax-task" id="kunena-normalizenames-start"
                            data-confirm="COM_KUNENA_NORMALIZENAMES_CONFIRM" data-progress="#kunena-normalizenames-progress"
                            data-url="<?php echo $escape($url); ?>">
                        <i class="icon-users icon-white"></i> <?php echo Text::_('COM_KUNENA_NORMALIZENAMES_BUTTON'); ?>
                    </button>

                    <div id="kunena-normalizenames-progress" style="display:none; margin-top:20px;">
                        <div class="progress progress-striped active">
                            <div class="bar" style="width: 1%;"></div>
                        </div>
                        <p class="kunena-ajax-message"></p>
                        <ul class="kunena-ajax-errors text-error"></ul>
                    </div>

                    <h3><?php echo Text::sprintf('COM_KUNENA_NORMALIZENAMES_PREVIEW', count($preview)); ?></h3>
                    <table class="table table-striped table-condensed" id="kunena-normalizenames-preview">
                        <thead>
                        <tr>
                            <th width="8%"><?php echo Text::_('JGRID_HEADING_ID'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_COL_USERNAME'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_NORMALIZENAMES_COL_NAME'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($preview as $id => $change) : ?>
                            <tr>
                                <td><?php echo (int) $id; ?></td>
                                <td>
                                    <?php if ($change[2] !== null) : ?>
                                        <del><?php echo $escape($change[0]); ?></del> &rarr; <strong><?php echo $escape($change[2]); ?></strong>
                                    <?php else : ?>
                                        <?php echo $escape($change[0]); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($change[3] !== null) : ?>
                                        <del><?php echo $escape($change[1]); ?></del> &rarr; <strong><?php echo $escape($change[3]); ?></strong>
                                    <?php else : ?>
                                        <?php echo $escape($change[1]); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </fieldset>
    </div>

    <div class="kunena-version small">
        <?php echo KunenaAdminVersion::getLongVersionHTML(); ?>
    </div>
</div>

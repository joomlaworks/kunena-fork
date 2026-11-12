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

Text::script('COM_KUNENA_SAMPLEDATA_INSTALL_CONFIRM');
Text::script('COM_KUNENA_SAMPLEDATA_REMOVE_CONFIRM');
Text::script('COM_KUNENA_AJAX_TASK_WORKING');
Text::script('COM_KUNENA_AJAX_ERROR');
Text::script('COM_KUNENA_AJAXMODAL_ERROR_TIMEOUT_BODY');
Text::script('COM_KUNENA_AJAXMODAL_ERROR_UNKNOWN_BODY');

HTMLHelper::_('jquery.framework');
Factory::getDocument()->addScript(Uri::root(true) . '/administrator/components/com_kunena/template/j3/tools/ajaxtask.js');

$token  = Session::getFormToken() . '=1';
$base   = 'index.php?option=com_kunena&view=tools&task=sampledata&format=json&' . $token . '&action=';
$counts = $this->sampleCounts;
$plan   = $this->samplePlan;
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
            <legend><?php echo Text::_('COM_KUNENA_SAMPLEDATA'); ?></legend>
            <p><?php echo Text::_('COM_KUNENA_SAMPLEDATA_DESC'); ?></p>

            <table class="table table-bordered table-striped" id="kunena-sampledata-counts">
                <thead>
                <tr>
                    <th></th>
                    <th><?php echo Text::_('COM_KUNENA_SAMPLEDATA_PLAN'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_SAMPLEDATA_INSTALLED'); ?></th>
                </tr>
                </thead>
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_SAMPLEDATA_CATEGORIES'); ?></td>
                    <td><?php echo (int) $plan['categories']; ?></td>
                    <td><strong><?php echo (int) $counts['category']; ?></strong></td>
                </tr>
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_SAMPLEDATA_USERS'); ?></td>
                    <td><?php echo (int) $plan['users']; ?></td>
                    <td><strong><?php echo (int) $counts['user']; ?></strong></td>
                </tr>
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_SAMPLEDATA_TOPICS'); ?></td>
                    <td><?php echo Text::sprintf('COM_KUNENA_SAMPLEDATA_TOPICS_POSTS', (int) $plan['topics'], (int) $plan['posts']); ?></td>
                    <td><strong><?php echo (int) $counts['topic']; ?></strong></td>
                </tr>
            </table>

            <?php if (!$this->canManage) : ?>
                <div class="alert alert-error"><?php echo Text::_('COM_KUNENA_SAMPLEDATA_NOT_ALLOWED'); ?></div>
            <?php elseif ($this->sampleInstalled) : ?>
                <div class="alert alert-info"><?php echo Text::_('COM_KUNENA_SAMPLEDATA_IS_INSTALLED'); ?></div>
                <button type="button" class="btn btn-danger btn-large kunena-ajax-task" id="kunena-sampledata-remove"
                        data-confirm="COM_KUNENA_SAMPLEDATA_REMOVE_CONFIRM" data-progress="#kunena-sampledata-progress" data-reload="1"
                        data-url="<?php echo htmlspecialchars($base . 'remove', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <i class="icon-trash icon-white"></i> <?php echo Text::_('COM_KUNENA_SAMPLEDATA_REMOVE_BUTTON'); ?>
                </button>
            <?php else : ?>
                <div class="alert alert-warning"><?php echo Text::_('COM_KUNENA_SAMPLEDATA_WARNING'); ?></div>
                <button type="button" class="btn btn-success btn-large kunena-ajax-task" id="kunena-sampledata-install"
                        data-confirm="COM_KUNENA_SAMPLEDATA_INSTALL_CONFIRM" data-progress="#kunena-sampledata-progress" data-reload="1"
                        data-url="<?php echo htmlspecialchars($base . 'install', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <i class="icon-database icon-white"></i> <?php echo Text::_('COM_KUNENA_SAMPLEDATA_INSTALL_BUTTON'); ?>
                </button>
            <?php endif; ?>

            <div id="kunena-sampledata-progress" style="display:none; margin-top:20px;">
                <div class="progress progress-striped active">
                    <div class="bar" style="width: 1%;"></div>
                </div>
                <p class="kunena-ajax-message"></p>
                <ul class="kunena-ajax-errors text-warning"></ul>
            </div>
        </fieldset>
    </div>

    <div class="kunena-version small">
        <?php echo KunenaAdminVersion::getLongVersionHTML(); ?>
    </div>
</div>

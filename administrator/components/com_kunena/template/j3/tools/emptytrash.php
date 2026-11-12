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

Text::script('COM_KUNENA_EMPTYTRASH_CONFIRM');
Text::script('COM_KUNENA_AJAX_TASK_WORKING');
Text::script('COM_KUNENA_AJAX_ERROR');
Text::script('COM_KUNENA_AJAXMODAL_ERROR_TIMEOUT_BODY');
Text::script('COM_KUNENA_AJAXMODAL_ERROR_UNKNOWN_BODY');

HTMLHelper::_('jquery.framework');
Factory::getDocument()->addScript(Uri::root(true) . '/administrator/components/com_kunena/template/j3/tools/ajaxtask.js');

$topics   = (int) $this->trashCounts['topics'];
$messages = (int) $this->trashCounts['messages'];
$url      = 'index.php?option=com_kunena&view=tools&task=emptytrash&format=json&' . Session::getFormToken() . '=1';
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
            <legend><?php echo Text::_('COM_KUNENA_EMPTYTRASH'); ?></legend>
            <p><?php echo Text::_('COM_KUNENA_EMPTYTRASH_DESC'); ?></p>

            <table class="table table-bordered table-striped" id="kunena-emptytrash-counts">
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_EMPTYTRASH_TOPICS'); ?></td>
                    <td><strong data-kunena-ajax-zero><?php echo $topics; ?></strong></td>
                </tr>
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_EMPTYTRASH_MESSAGES'); ?></td>
                    <td><strong data-kunena-ajax-zero><?php echo $messages; ?></strong></td>
                </tr>
            </table>

            <?php if (!$this->canEmpty) : ?>
                <div class="alert alert-error"><?php echo Text::_('COM_KUNENA_EMPTYTRASH_NOT_ALLOWED'); ?></div>
            <?php elseif (!$topics && !$messages) : ?>
                <div class="alert alert-success"><?php echo Text::_('COM_KUNENA_EMPTYTRASH_EMPTY'); ?></div>
            <?php else : ?>
                <div class="alert alert-warning"><?php echo Text::_('COM_KUNENA_EMPTYTRASH_WARNING'); ?></div>
                <button type="button" class="btn btn-danger btn-large kunena-ajax-task" id="kunena-emptytrash-start"
                        data-confirm="COM_KUNENA_EMPTYTRASH_CONFIRM" data-progress="#kunena-emptytrash-progress"
                        data-url="<?php echo htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <i class="icon-trash icon-white"></i> <?php echo Text::_('COM_KUNENA_EMPTYTRASH_BUTTON'); ?>
                </button>

                <div id="kunena-emptytrash-progress" style="display:none; margin-top:20px;">
                    <div class="progress progress-striped active">
                        <div class="bar" style="width: 1%;"></div>
                    </div>
                    <p class="kunena-ajax-message"></p>
                    <ul class="kunena-ajax-errors text-error"></ul>
                </div>
            <?php endif; ?>
        </fieldset>
    </div>

    <div class="kunena-version small">
        <?php echo KunenaAdminVersion::getLongVersionHTML(); ?>
    </div>
</div>

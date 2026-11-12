<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator.Template
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var KunenaAdminViewBaldrick $this */

$n = function ($value) {
    return number_format((int) $value);
};

$settings = $this->plugin ? Route::_('index.php?option=com_plugins&task=plugin.edit&extension_id=' . (int) $this->plugin->extension_id) : '';
$actions  = array(
    'replied'          => 'success',
    'canned'           => 'info',
    'info'             => 'info',
    'spam'             => 'warning',
    'spam_unpublished' => 'important',
    'skipped'          => '',
    'error'            => 'important',
);
?>

<div id="kunena" class="admin override">
    <div id="j-sidebar-container" class="span2">
        <div id="sidebar">
            <div class="sidebar-nav"><?php include KPATH_ADMIN . '/template/j3/common/menu.php'; ?></div>
        </div>
    </div>
    <div id="j-main-container" class="span10">
        <form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=baldrick') ?>"
              method="post" id="adminForm" name="adminForm">
            <input type="hidden" name="task" value=""/>
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>

        <p><?php echo Text::_('COM_KUNENA_BALDRICK_DESC'); ?></p>

        <?php if (!$this->plugin) : ?>
            <div class="alert alert-error"><?php echo Text::_('COM_KUNENA_BALDRICK_NOT_INSTALLED'); ?></div>
        <?php else : ?>
            <table class="table table-bordered" id="kunena-baldrick-status">
                <tr>
                    <td width="30%"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS'); ?></td>
                    <td>
                        <?php if (!$this->enabled) : ?>
                            <span class="label"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS_DISABLED'); ?></span>
                        <?php elseif (!$this->configured) : ?>
                            <span class="label label-warning"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS_NOT_CONFIGURED'); ?></span>
                        <?php else : ?>
                            <span class="label label-success"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS_ACTIVE'); ?></span>
                        <?php endif; ?>
                        <?php $pause = KunenaBaldrick::getPause(); ?>
                        <?php if ($this->enabled && $pause) : ?>
                            <span class="label label-important"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS_PAUSED'); ?></span>
                            <span class="small" id="kunena-baldrick-paused"><?php echo $this->escape(Text::sprintf('COM_KUNENA_BALDRICK_PAUSED_SINCE', HTMLHelper::_('date', $pause['since'], Text::_('DATE_FORMAT_LC2')), $pause['reason'])); ?></span>
                        <?php endif; ?>
                        <?php if ((int) $this->params->get('dry_run', 0)) : ?>
                            <span class="label label-info"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS_DRY_RUN'); ?></span>
                        <?php endif; ?>
                        <?php if ($this->canManage && $settings) : ?>
                            &nbsp;<a class="btn btn-small" href="<?php echo $settings; ?>"><i class="icon-options"></i> <?php echo Text::_('COM_KUNENA_BALDRICK_SETTINGS'); ?></a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_BALDRICK_MODELS'); ?></td>
                    <td>
                        <?php
                        $triage = KunenaBaldrick::getConnection('triage');
$answer = KunenaBaldrick::getConnection('answer');
echo $this->escape($triage['model']) . ' &rarr; ' . $this->escape($answer['model']);
?>
                    </td>
                </tr>
                <?php if ($this->hasTables) : ?>
                    <tr>
                        <td><?php echo Text::_('COM_KUNENA_BALDRICK_SEARCH_INDEX'); ?></td>
                        <td id="kunena-baldrick-index">
                            <?php if ($this->indexStatus === 'present') : ?>
                                <span class="label label-success"><?php echo Text::_('COM_KUNENA_BALDRICK_INDEX_PRESENT'); ?></span>
                                <span class="small"><?php echo Text::sprintf('COM_KUNENA_BALDRICK_INDEX_PRESENT_DESC', $n($this->postCount)); ?></span>
                            <?php elseif ($this->indexStatus === 'missing') : ?>
                                <span class="label label-warning"><?php echo Text::_('COM_KUNENA_BALDRICK_INDEX_MISSING'); ?></span>
                                <span class="small"><?php echo Text::sprintf('COM_KUNENA_BALDRICK_INDEX_MISSING_DESC', $n($this->postCount), $n((int) $this->params->get('retrieval_max_posts', 50000))); ?></span>
                                <?php if ($this->canManage) : ?>
                                    <form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=baldrick') ?>" method="post" style="margin:6px 0 0;">
                                        <input type="hidden" name="task" value="createindex"/>
                                        <?php echo HTMLHelper::_('form.token'); ?>
                                        <button type="submit" class="btn btn-small" id="kunena-baldrick-createindex"
                                                onclick="return confirm(<?php echo $this->escape(json_encode(Text::_('COM_KUNENA_BALDRICK_INDEX_CONFIRM'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)); ?>);">
                                            <i class="icon-lightning"></i> <?php echo Text::_('COM_KUNENA_BALDRICK_INDEX_CREATE'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="label"><?php echo Text::_('COM_KUNENA_BALDRICK_INDEX_UNSUPPORTED'); ?></span>
                                <span class="small"><?php echo Text::sprintf('COM_KUNENA_BALDRICK_INDEX_UNSUPPORTED_DESC', $n((int) $this->params->get('retrieval_max_posts', 50000))); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo Text::_('COM_KUNENA_BALDRICK_PROCESSING'); ?></td>
                    <td><?php echo Text::_($this->params->get('processing', 'auto') === 'auto' ? 'COM_KUNENA_BALDRICK_PROCESSING_AUTO' : 'COM_KUNENA_BALDRICK_PROCESSING_CRON'); ?></td>
                </tr>
            </table>
        <?php endif; ?>

        <?php if (!$this->hasTables) : ?>
            <div class="alert alert-warning"><?php echo Text::_('COM_KUNENA_BALDRICK_NO_TABLES'); ?></div>
        <?php else : ?>
            <?php $t = $this->totals; ?>
            <h3><?php echo Text::_('COM_KUNENA_BALDRICK_TOTALS'); ?></h3>
            <table class="table table-bordered table-striped" id="kunena-baldrick-totals">
                <thead>
                <tr>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_COST'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_REQUESTS'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_INPUT_TOKENS'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_OUTPUT_TOKENS'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_CACHE_TOKENS'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_REPLIES'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_CANNED'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_INFO_REQUESTS'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_SPAM'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_SKIPPED'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_ERRORS'); ?></th>
                </tr>
                </thead>
                <tr>
                    <td><strong data-baldrick="cost"><?php echo KunenaBaldrickUsage::formatCost($t->cost); ?></strong></td>
                    <td data-baldrick="requests"><?php echo $n($t->requests); ?></td>
                    <td data-baldrick="input"><?php echo $n($t->input_tokens); ?></td>
                    <td data-baldrick="output"><?php echo $n($t->output_tokens); ?></td>
                    <td><?php echo $n($t->cache_read_tokens) . ' / ' . $n($t->cache_write_tokens); ?></td>
                    <td data-baldrick="replies"><?php echo $n($t->replies); ?></td>
                    <td data-baldrick="canned"><?php echo $n($t->canned); ?></td>
                    <td data-baldrick="info"><?php echo $n($t->info_requests); ?></td>
                    <td data-baldrick="spam"><?php echo $n($t->spam); ?></td>
                    <td data-baldrick="skipped"><?php echo $n($t->skipped); ?></td>
                    <td data-baldrick="errors"><?php echo $n($t->errors); ?></td>
                </tr>
            </table>
            <p class="small muted"><?php echo Text::_('COM_KUNENA_BALDRICK_TOTALS_NOTE'); ?></p>

            <h3><?php echo Text::_('COM_KUNENA_BALDRICK_QUEUE'); ?></h3>
            <p id="kunena-baldrick-queue">
                <?php echo Text::sprintf('COM_KUNENA_BALDRICK_QUEUE_STATUS', (int) $this->queue['pending'], (int) $this->queue['due'], (int) $this->queue['processing'], (int) $this->queue['failed']); ?>
            </p>
            <?php if ($this->canManage && $this->queue['failed']) : ?>
                <form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=baldrick') ?>" method="post" style="display:inline-block; margin-right:8px;">
                    <input type="hidden" name="task" value="retryfailed"/>
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn" id="kunena-baldrick-retryfailed">
                        <i class="icon-refresh"></i> <?php echo Text::sprintf('COM_KUNENA_BALDRICK_RETRY_FAILED', (int) $this->queue['failed']); ?>
                    </button>
                </form>
                <form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=baldrick') ?>" method="post" style="display:inline-block;">
                    <input type="hidden" name="task" value="discardfailed"/>
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn" id="kunena-baldrick-discardfailed">
                        <i class="icon-trash"></i> <?php echo Text::sprintf('COM_KUNENA_BALDRICK_DISCARD_FAILED', (int) $this->queue['failed']); ?>
                    </button>
                </form>
                <p class="small muted" style="margin-top:6px;"><?php echo Text::_('COM_KUNENA_BALDRICK_FAILED_NOTE'); ?></p>
            <?php endif; ?>
            <?php if ($this->canManage && $this->queue['due']) : ?>
                <form action="<?php echo KunenaRoute::_('administrator/index.php?option=com_kunena&view=baldrick') ?>" method="post">
                    <input type="hidden" name="task" value="process"/>
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn btn-primary" id="kunena-baldrick-process">
                        <i class="icon-play icon-white"></i> <?php echo Text::_('COM_KUNENA_BALDRICK_PROCESS_NOW'); ?>
                    </button>
                </form>
            <?php endif; ?>

            <div class="row-fluid">
                <div class="span7">
                    <h3><?php echo Text::_('COM_KUNENA_BALDRICK_MONTHS'); ?></h3>
                    <table class="table table-bordered table-striped table-condensed" id="kunena-baldrick-months">
                        <thead>
                        <tr>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_MONTH'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_COST'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_REQUESTS'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_TOKENS_IN_OUT'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_REPLIES'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_SPAM'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_ERRORS'); ?></th>
                        </tr>
                        </thead>
                        <?php if (!$this->months) : ?>
                            <tr><td colspan="7"><?php echo Text::_('COM_KUNENA_BALDRICK_NOTHING_YET'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($this->months as $month) : ?>
                            <tr>
                                <td><?php echo $this->escape($month->month); ?></td>
                                <td><?php echo KunenaBaldrickUsage::formatCost($month->cost); ?></td>
                                <td><?php echo $n($month->requests); ?></td>
                                <td><?php echo $n($month->input_tokens + $month->cache_read_tokens + $month->cache_write_tokens) . ' / ' . $n($month->output_tokens); ?></td>
                                <td><?php echo $n($month->replies + $month->canned + $month->info_requests); ?></td>
                                <td><?php echo $n($month->spam); ?></td>
                                <td><?php echo $n($month->errors); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="span5">
                    <h3><?php echo Text::_('COM_KUNENA_BALDRICK_PER_MODEL'); ?></h3>
                    <table class="table table-bordered table-striped table-condensed" id="kunena-baldrick-models">
                        <thead>
                        <tr>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_MODEL'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_COST'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_REQUESTS'); ?></th>
                            <th><?php echo Text::_('COM_KUNENA_BALDRICK_TOKENS_IN_OUT'); ?></th>
                        </tr>
                        </thead>
                        <?php if (!$this->models) : ?>
                            <tr><td colspan="4"><?php echo Text::_('COM_KUNENA_BALDRICK_NOTHING_YET'); ?></td></tr>
                        <?php endif; ?>
                        <?php foreach ($this->models as $model) : ?>
                            <tr>
                                <td><?php echo $this->escape($model->model); ?></td>
                                <td><?php echo KunenaBaldrickUsage::formatCost($model->cost); ?></td>
                                <td><?php echo $n($model->requests); ?></td>
                                <td><?php echo $n($model->input_tokens + $model->cache_read_tokens + $model->cache_write_tokens) . ' / ' . $n($model->output_tokens); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>

            <h3><?php echo Text::_('COM_KUNENA_BALDRICK_LOG'); ?></h3>
            <table class="table table-striped table-condensed" id="kunena-baldrick-log">
                <thead>
                <tr>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_TIME'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_THREAD'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_ACTION'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_MODELS'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_TOKENS_IN_OUT'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_COST'); ?></th>
                    <th><?php echo Text::_('COM_KUNENA_BALDRICK_DETAILS'); ?></th>
                </tr>
                </thead>
                <?php if (!$this->log) : ?>
                    <tr><td colspan="7"><?php echo Text::_('COM_KUNENA_BALDRICK_NOTHING_YET'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($this->log as $row) :
                    $url   = $this->topicUrl($row->topic_id);
                    $label = isset($actions[$row->action]) ? $actions[$row->action] : '';
                    ?>
                    <tr>
                        <td class="nowrap small"><?php echo HTMLHelper::_('date', (int) $row->time, 'Y-m-d H:i:s'); ?></td>
                        <td>
                            <?php if ($url) : ?>
                                <a href="<?php echo $this->escape($url); ?>" target="_blank" rel="noopener">#<?php echo (int) $row->topic_id; ?></a>
                            <?php else : ?>
                                #<?php echo (int) $row->topic_id; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="label<?php echo $label ? ' label-' . $label : ''; ?>"><?php echo $this->escape(Text::_('COM_KUNENA_BALDRICK_ACTION_' . strtoupper($row->action))); ?></span>
                            <?php if ($row->dry_run) : ?><span class="label label-info"><?php echo Text::_('COM_KUNENA_BALDRICK_STATUS_DRY_RUN'); ?></span><?php endif; ?>
                        </td>
                        <td class="small"><?php echo $this->escape($row->models); ?></td>
                        <td class="small nowrap"><?php echo $n($row->input_tokens + $row->cache_tokens) . ' / ' . $n($row->output_tokens); ?></td>
                        <td class="small"><?php echo KunenaBaldrickUsage::formatCost($row->cost); ?></td>
                        <td class="small"><?php echo $this->escape($row->message); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <?php if ($this->logSize > $this->perPage) : ?>
                <ul class="pager">
                    <?php if ($this->offset > 0) : ?>
                        <li class="previous"><a href="<?php echo Route::_('index.php?option=com_kunena&view=baldrick&limitstart=' . max(0, $this->offset - $this->perPage)); ?>">&larr; <?php echo Text::_('JPREV'); ?></a></li>
                    <?php endif; ?>
                    <?php if ($this->offset + $this->perPage < $this->logSize) : ?>
                        <li class="next"><a href="<?php echo Route::_('index.php?option=com_kunena&view=baldrick&limitstart=' . ($this->offset + $this->perPage)); ?>"><?php echo Text::_('JNEXT'); ?> &rarr;</a></li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="pull-right small">
        <?php echo KunenaAdminVersion::getLongVersionHTML(); ?>
    </div>
</div>

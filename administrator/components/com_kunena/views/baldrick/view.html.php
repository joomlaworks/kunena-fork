<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator
 * @subpackage    Views
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * Baldrick AI: status, totals (all kept months, per month, per model), queue and activity log.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaAdminViewBaldrick extends KunenaView
{
    /**
     * Log rows per page
     *
     * @since 5.3.0
     */
    public const PER_PAGE = 50;

    /**
     * @return  void
     *
     * @since 5.3.0
     */
    public function displayDefault()
    {
        KunenaBaldrickEngine::loadLanguage();

        if (KunenaBaldrick::hasTables()) {
            KunenaBaldrick::migrateSignature();
        }

        $user = Factory::getUser();

        $this->plugin     = KunenaBaldrick::getPlugin();
        $this->enabled    = KunenaBaldrick::isEnabled();
        $this->configured = KunenaBaldrick::isConfigured();
        $this->params     = KunenaBaldrick::getParams();
        $this->hasTables  = KunenaBaldrick::hasTables();
        $this->canManage  = $user->authorise('core.admin', 'com_kunena') || $user->authorise('core.manage', 'com_plugins');
        $this->offset     = max(0, $this->app->input->getInt('limitstart', 0));
        $this->perPage    = static::PER_PAGE;

        if ($this->hasTables) {
            $this->indexStatus = KunenaBaldrickRetriever::indexStatus();
            $this->postCount   = (int) Factory::getDbo()->setQuery('SELECT COUNT(*) FROM ' . Factory::getDbo()->quoteName('#__kunena_messages'))->loadResult();
            $this->totals  = KunenaBaldrickUsage::getTotals();
            $this->months  = KunenaBaldrickUsage::getMonths();
            $this->models  = KunenaBaldrickUsage::getModels();
            $this->log     = KunenaBaldrickUsage::getLog($this->offset, static::PER_PAGE);
            $this->logSize = KunenaBaldrickUsage::countLog();
            $this->queue   = array(
                'due'        => KunenaBaldrickQueue::countDue(),
                'pending'    => KunenaBaldrickQueue::countStatus('pending'),
                'processing' => KunenaBaldrickQueue::countStatus('processing'),
                'failed'     => KunenaBaldrickQueue::countStatus('failed'),
            );
        }

        JToolbarHelper::title(Text::_('COM_KUNENA') . ': ' . Text::_('COM_KUNENA_BALDRICK'), 'comments-2');

        $this->display();
    }

    /**
     * @param   integer  $topicId  Thread
     *
     * @return  string  Link to the thread on the site (empty if it no longer exists)
     *
     * @since 5.3.0
     */
    public function topicUrl($topicId)
    {
        $topic = KunenaForumTopicHelper::get((int) $topicId);

        if (!$topic->exists()) {
            return '';
        }

        return \Joomla\CMS\Uri\Uri::root() . KunenaBaldrick::topicLink($topic->category_id, $topic->id);
    }
}

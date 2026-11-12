<?php

/**
 * Kunena - Baldrick AI
 *
 * @package       Kunena.Plugins
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Baldrick, the forum's AI first responder.
 *
 * The plugin only wires events; the logic lives in Kunena's library (libraries/kunena/baldrick), shared with the
 * administrator (Kunena > Baldrick AI) and the command line (kunena:baldrick:process).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class PlgKunenaBaldrick extends CMSPlugin
{
    /**
     * @var boolean
     * @since 5.3.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return  boolean  Kunena and Baldrick's library are available
     *
     * @since 5.3.0
     */
    protected function ready()
    {
        return class_exists('KunenaForum') && KunenaForum::installed() && class_exists('KunenaBaldrick');
    }

    /**
     * Gives Kunena the activity integration which sees posts becoming visible.
     *
     * @return  KunenaBaldrickActivity|null
     *
     * @since 5.3.0
     */
    public function onKunenaGetActivity()
    {
        if (!$this->ready()) {
            return null;
        }

        return new KunenaBaldrickActivity();
    }

    /**
     * Processes the queue after the response has been sent: when this request queued a thread, and on forum pages while
     * due jobs are waiting (e.g. after a temporary error).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function onAfterRespond()
    {
        if (!$this->ready() || !KunenaBaldrick::hasTables() || (string) $this->params->get('processing', 'auto') !== 'auto') {
            return;
        }

        $app    = Factory::getApplication();
        $option = $app->input->getCmd('option');

        if ($option === 'com_ajax' || !($app->isClient('site') || $app->isClient('administrator'))) {
            return;
        }

        if (!KunenaBaldrick::$queued) {
            if (!$app->isClient('site') || $option !== 'com_kunena' || !KunenaBaldrick::isConfigured() || !KunenaBaldrickQueue::countDue()) {
                return;
            }
        }

        KunenaBaldrick::kick();
    }

    /**
     * Background request processing the queue (com_ajax, started by KunenaBaldrick::kick()).
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public function onAjaxBaldrick()
    {
        $input = Factory::getApplication()->input;

        if ($input->getCmd('task') !== 'run' || !$this->ready()) {
            return '';
        }

        if (!hash_equals(KunenaBaldrick::runKey(), (string) $input->post->getString('key', ''))) {
            return 'denied';
        }

        // The caller does not wait for the answer
        ignore_user_abort(true);
        @set_time_limit(300);

        KunenaBaldrick::releaseSession();
        KunenaForum::setup();

        return (string) count(KunenaBaldrickEngine::run(3, 240));
    }
}

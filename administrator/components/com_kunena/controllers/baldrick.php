<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Administrator
 * @subpackage    Controllers
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\String\StringHelper;

/**
 * Baldrick AI: activity page, processing the queue on demand, and the AJAX helpers of the plugin's settings (list the
 * models of a provider, test a connection).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaAdminControllerBaldrick extends KunenaController
{
    /**
     * @var string
     * @since 5.3.0
     */
    protected $baseurl = 'administrator/index.php?option=com_kunena&view=baldrick';

    /**
     * @return  boolean  The user may configure Baldrick
     *
     * @since 5.3.0
     */
    protected function canConfigure()
    {
        $user = Factory::getUser();

        return $user->authorise('core.admin', 'com_kunena') || $user->authorise('core.manage', 'com_plugins');
    }

    /**
     * Processes the due jobs of the queue now.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function process()
    {
        KunenaBaldrickEngine::loadLanguage();

        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
        } elseif (!$this->canConfigure()) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_BALDRICK_NOT_ALLOWED'), 'error');
        } else {
            @set_time_limit(300);
            $results = KunenaBaldrickEngine::run(10, 240);

            $this->app->enqueueMessage(Text::sprintf('COM_KUNENA_BALDRICK_PROCESSED', count($results)));

            foreach ($results as $id => $result) {
                $this->app->enqueueMessage('#' . (int) $id . ': ' . $result, strpos($result, 'failed') === 0 ? 'warning' : 'message');
            }
        }

        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * Creates the full-text index used to find related threads quickly on large forums.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function createindex()
    {
        KunenaBaldrickEngine::loadLanguage();

        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
        } elseif (!$this->canConfigure()) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_BALDRICK_NOT_ALLOWED'), 'error');
        } else {
            @set_time_limit(0);
            $start = microtime(true);

            try {
                KunenaBaldrickRetriever::createIndex();
                $this->app->enqueueMessage(Text::sprintf('COM_KUNENA_BALDRICK_INDEX_CREATED', round(microtime(true) - $start, 1)));
            } catch (Exception $e) {
                $this->app->enqueueMessage(Text::sprintf('COM_KUNENA_BALDRICK_INDEX_FAILED', $e->getMessage()), 'error');
            }
        }

        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * Puts the failed threads back in the queue.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function retryfailed()
    {
        $this->failedJobs(true);
    }

    /**
     * Removes the failed threads from the queue.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function discardfailed()
    {
        $this->failedJobs(false);
    }

    /**
     * @param   boolean  $retry  Retry or discard
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected function failedJobs($retry)
    {
        KunenaBaldrickEngine::loadLanguage();

        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
        } elseif (!$this->canConfigure()) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_BALDRICK_NOT_ALLOWED'), 'error');
        } else {
            $count = KunenaBaldrickQueue::failed($retry);
            $this->app->enqueueMessage(Text::sprintf($retry ? 'COM_KUNENA_BALDRICK_FAILED_RETRIED' : 'COM_KUNENA_BALDRICK_FAILED_DISCARDED', $count));
        }

        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * Lists the models of a connection (AJAX, from the plugin's settings).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function models()
    {
        $client = $this->ajaxClient();
        $input  = $this->app->input->post;

        // Long lists (OpenRouter offers hundreds of models) are kept for an hour, per endpoint and key
        $key   = $client->getProvider() . '|' . $input->getString('base_url', '') . '|' . hash('sha256', $client->getApiKey());
        $cache  = \Joomla\CMS\Cache\Cache::getInstance('', array('defaultgroup' => 'com_kunena_baldrick', 'caching' => true, 'lifetime' => 60));
        $id     = 'models-' . md5($key);
        $cached = $input->getInt('refresh', 0) ? false : json_decode((string) $cache->get($id), true);

        if (is_array($cached) && isset($cached['time'], $cached['models'])) {
            $this->respond(array(
                'success' => true,
                'models'  => $cached['models'],
                'message' => Text::sprintf('COM_KUNENA_BALDRICK_MODELS_CACHED', count($cached['models']), (int) floor((time() - $cached['time']) / 60)),
            ));

            return;
        }

        try {
            $models = $client->listModels();
        } catch (Exception $e) {
            $this->respond(array('success' => false, 'message' => $e->getMessage()));

            return;
        }

        $cache->store(json_encode(array('time' => time(), 'models' => $models)), $id);

        $message = Text::sprintf('COM_KUNENA_BALDRICK_MODELS_FOUND', count($models));

        if ($client->modelListUrl !== '') {
            $message .= ' ' . Text::sprintf('COM_KUNENA_BALDRICK_MODELS_FROM', $client->modelListUrl);
        }

        $this->respond(array(
            'success' => true,
            'models'  => $models,
            'message' => $message,
        ));
    }

    /**
     * Sends a tiny request to the selected model (AJAX, from the plugin's settings).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function test()
    {
        $client = $this->ajaxClient();

        try {
            $result = $client->complete('You are a connection test. Reply with the single word OK.', 'Reply with OK.');
        } catch (Exception $e) {
            $this->respond(array('success' => false, 'message' => $e->getMessage()));

            return;
        }

        // Tests cost tokens too (the tables exist once Kunena's wizard has run)
        if (KunenaBaldrick::hasTables()) {
            KunenaBaldrickUsage::addRequest($result['model'], $result['usage'], $result['cost']);
        }

        $this->respond(array(
            'success' => true,
            'message' => Text::sprintf(
                'COM_KUNENA_BALDRICK_TEST_OK',
                $result['model'],
                $this->cut($result['text'] !== '' ? $result['text'] : '(' . $result['stop'] . ')', 60),
                (int) $result['duration'],
                (int) $result['usage']['input'] + (int) $result['usage']['cache_read'] + (int) $result['usage']['cache_write'],
                (int) $result['usage']['output']
            ),
        ));
    }

    /**
     * Checks an AJAX request and builds the client from the (possibly unsaved) settings it carries.
     *
     * @return  KunenaBaldrickClient
     *
     * @since 5.3.0
     */
    protected function ajaxClient()
    {
        KunenaBaldrickEngine::loadLanguage();

        if (!Session::checkToken('post')) {
            $this->respond(array('success' => false, 'message' => Text::_('COM_KUNENA_ERROR_TOKEN')));
        }

        if (!$this->canConfigure()) {
            $this->respond(array('success' => false, 'message' => Text::_('COM_KUNENA_BALDRICK_NOT_ALLOWED')));
        }

        $input = $this->app->input->post;
        $stage = $input->getCmd('stage') === 'answer' ? 'answer' : 'triage';
        $saved = KunenaBaldrick::getConnection($stage);
        $key   = trim($input->getString('api_key', ''));
        $url   = trim($input->getString('base_url', ''));

        if (!KunenaBaldrickClient::isValidBaseUrl($url)) {
            $this->respond(array('success' => false, 'message' => Text::_('COM_KUNENA_BALDRICK_INVALID_URL')));
        }

        return new KunenaBaldrickClient(array(
            'provider'   => $input->getCmd('provider') === 'openai' ? 'openai' : 'anthropic',
            'base_url'   => $url,
            // An empty field means the saved key
            'api_key'    => $key !== '' ? $key : $saved['api_key'],
            'model'      => trim($input->getString('model', '')),
            'max_tokens' => 256,
            'price_in'   => $saved['price_in'],
            'price_out'  => $saved['price_out'],
            'timeout'    => 30,
            'connect'    => 10,
            'fallbacks'  => 0,
        ));
    }

    /**
     * @param   array  $data  Response
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected function respond(array $data)
    {
        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        jexit();
    }

    /**
     * @param   string   $text    Text
     * @param   integer  $length  Length
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected function cut($text, $length)
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

        return StringHelper::strlen($text) > $length ? StringHelper::substr($text, 0, $length) . '...' : $text;
    }
}

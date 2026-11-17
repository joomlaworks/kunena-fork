<?php

/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Controllers
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

/**
 * Kunena Cpanel Controller
 *
 * @since  2.0
 */
#[\AllowDynamicProperties]
class KunenaAdminControllerTools extends KunenaController
{
    /**
     * @var null|string
     *
     * @since    2.0
     */
    protected $baseurl = null;

    /**
     * Construct
     *
     * @param   array $config config
     *
     * @throws Exception
     * @since    2.0
     */
    public function __construct($config = array())
    {
        parent::__construct($config);
        $this->baseurl = 'administrator/index.php?option=com_kunena&view=tools';
    }

    /**
     * Diagnotics
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0
     * @throws null
     */
    public function diagnostics()
    {
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
            $this->setRedirect(KunenaRoute::_($this->baseurl, false));

            return;
        }

        $fix    = $this->app->input->getCmd('fix');
        $delete = $this->app->input->getCmd('delete');

        if ($fix) {
            $success = KunenaForumDiagnostics::fix($fix);

            if (!$success) {
                $this->app->enqueueMessage(Text::sprintf('Failed to fix %s!', $fix), 'error');
            }
        } elseif ($delete) {
            $success = KunenaForumDiagnostics::delete($delete);

            if (!$success) {
                $this->app->enqueueMessage(Text::sprintf('Failed to delete %s!', $delete), 'error');
            }
        }

        $this->setRedirect(KunenaRoute::_($this->baseurl . '&layout=diagnostics', false));
    }

    /**
     * Set proper response for both AJAX and traditional calls.
     *
     * @param   $response
     * @param   $ajax
     *
     * @return void
     *
     * @since    2.0
     */
    protected function setResponse($response, $ajax)
    {
        if (!$ajax) {
            if (!empty($response['error'])) {
                $this->setMessage($response['error'], 'error');
            }

            if (!empty($response['href'])) {
                $this->setRedirect($response['href']);
            }
        } else {
            if (ob_get_length()) {
                ob_end_clean();
            }

            header('Content-type: application/json');
            echo json_encode($response);
            flush();
            jexit();
        }
    }

    /**
     * Trash Menu
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0
     * @throws null
     */
    public function trashmenu()
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
            $this->setRedirect(KunenaRoute::_($this->baseurl, false));

            return;
        }

        require_once KPATH_ADMIN . '/install/model.php';

        $installer = new KunenaModelInstall();
        $installer->deleteMenu();
        $installer->createMenu();

        $this->app->enqueueMessage(Text::_('COM_KUNENA_MENU_CREATED'));
        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * Fix Legacy
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0
     * @throws null
     */
    public function fixlegacy()
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
            $this->setRedirect(KunenaRoute::_($this->baseurl, false));

            return;
        }

        $legacy = KunenaMenuFix::getLegacy();
        $errors = KunenaMenuFix::fixLegacy();

        if ($errors) {
            $this->app->enqueueMessage(Text::sprintf('COM_KUNENA_MENU_FIXED_LEGACY_FAILED', $errors[0]), 'notice');
        } else {
            $this->app->enqueueMessage(Text::sprintf('COM_KUNENA_MENU_FIXED_LEGACY', count($legacy)));
        }

        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * Install or remove the sample data (AJAX, in steps).
     *
     * Request: task=sampledata&format=json&action=install|remove&step=init|run plus a form token. Answers JSON with
     * the progress ("status"), a message, notes ("errors": items kept on removal) and "done".
     *
     * @return void
     *
     * @throws Exception
     * @since 5.3.0 (JoomlaWorks Ltd.)
     */
    public function sampledata()
    {
        KunenaFactory::loadLanguage('com_kunena.views', 'admin');

        $fail = function ($message) {
            $this->setResponse(
                array('success' => false, 'header' => Text::_('COM_KUNENA_AJAX_ERROR'), 'message' => $message),
                true
            );
        };

        if (!Session::checkToken('request')) {
            $fail(Text::_('COM_KUNENA_ERROR_TOKEN'));

            return;
        }

        if (!Factory::getUser()->authorise('core.admin', 'com_kunena')) {
            $fail(Text::_('COM_KUNENA_SAMPLEDATA_NOT_ALLOWED'));

            return;
        }

        $action = $this->input->getCmd('action') === 'remove' ? 'remove' : 'install';
        $key    = 'com_kunena.admin.sampledata.' . $action;
        $state  = $this->app->getUserState($key, null);

        if ($this->input->getCmd('step', 'run') === 'init' || !is_array($state)) {
            $state = array();
        }

        try {
            $result = $action === 'install' ? KunenaSampledata::install(10, $state) : KunenaSampledata::remove(10, $state);
        } catch (Exception $e) {
            $this->app->setUserState($key, null);
            $fail($e->getMessage());

            return;
        }

        $this->app->setUserState($key, $result['done'] ? null : $state);

        if ($result['done']) {
            $message = Text::_($action === 'install' ? 'COM_KUNENA_SAMPLEDATA_INSTALLED_DONE' : 'COM_KUNENA_SAMPLEDATA_REMOVED_DONE');
        } else {
            $message = $result['message'];
        }

        $this->setResponse(
            array(
                'success' => true,
                'done'    => (bool) $result['done'],
                'status'  => (int) $result['progress'] . '%',
                'message' => $message,
                'errors'  => isset($result['kept']) ? $result['kept'] : array(),
            ),
            true
        );
    }

    /**
     * Method to just redirect to main manager in case of use of cancel button
     *
     * @return void
     *
     * @throws Exception
     * @since K4.0
     * @throws null
     */
    public function cancel()
    {
        $this->app->redirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * Method to completely remove kunena by checking before if the user is a super-administrator
     *
     * @return void
     *
     * @throws Exception
     * @since K4.0
     * @throws null
     */
    public function uninstall()
    {
        if (!Session::checkToken('post')) {
            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');
            $this->setRedirect(KunenaRoute::_($this->baseurl, false));

            return;
        }

        // Check if the user has the super-administrator rights: the current user must be a super user and must
        // re-confirm his own password. Never log in as (or trust the result of logging in as) another account.
        $username = $this->app->input->getString('username');
        $password = $this->app->input->post->get('password', '', 'raw');

        $user   = Factory::getUser();
        $isroot = !$user->guest && $user->authorise('core.admin');
        $error  = true;

        if ($isroot && is_string($username) && $username !== '' && $username === $user->username && is_string($password) && $password !== '') {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select($db->quoteName('password'))
                ->from($db->quoteName('#__users'))
                ->where($db->quoteName('id') . ' = ' . (int) $user->id);
            $db->setQuery($query);
            $hash = (string) $db->loadResult();

            if ($hash !== '' && \Joomla\CMS\User\UserHelper::verifyPassword($password, $hash, $user->id)) {
                $error = false;
            }
        }

        if (!$error && $isroot) {
            $this->app->setUserState('com_kunena.uninstall.allowed', true);

            $this->setRedirect(KunenaRoute::_('administrator/index.php?option=com_kunena&view=uninstall&' . Session::getFormToken() . '=1', false));

            return;
        }

        $this->app->enqueueMessage(Text::_('COM_KUNENA_TOOLS_UNINSTALL_LOGIN_FAILED'));
        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }

    /**
     * System Report
     *
     * @return void
     *
     * @throws Exception
     * @since    2.0
     * @throws null
     */
    public function systemreport()
    {
        $this->setRedirect(KunenaRoute::_($this->baseurl, false));
    }
}

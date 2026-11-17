<?php

/**
 * Kunena Component
 *
 * @package         Kunena.Administrator
 * @subpackage      Views
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * About view for Kunena cpanel
 * @since Kunena
 */
#[\AllowDynamicProperties]
class KunenaAdminViewTools extends KunenaView
{
    /**
     * @since Kunena
     */
    public function displayDefault()
    {
        $this->setToolBarDefault();
        $this->display();
    }

    /**
     * @since Kunena
     */
    protected function setToolBarDefault()
    {
        JToolbarHelper::title(Text::_('COM_KUNENA') . ': ' . Text::_('COM_KUNENA_FORUM_TOOLS'), 'tools');
        $help_url = 'https://docs.kunena.org/en/manual/backend/tools';
        JToolbarHelper::help('COM_KUNENA', false, $help_url);
    }

    /**
     * The maintenance tools' old pages (Prune, Synchronize Users, Recount, Purge RE:, Cleanup IP, Empty Trash, Normalise
     * Usernames & Names) are jobs of Kunena > Maintenance since 5.5.0: old links and bookmarks go there.
     *
     * @return void
     * @since 5.5.0
     */
    protected function toMaintenance()
    {
        Factory::getApplication()->redirect(\Joomla\CMS\Router\Route::_('index.php?option=com_kunena&view=maintenance', false));
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displayPrune()
    {
        $this->toMaintenance();
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displaySyncUsers()
    {
        $this->toMaintenance();
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displayRecount()
    {
        $this->toMaintenance();
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displayPurgeReStatements()
    {
        $this->toMaintenance();
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displayCleanupIP()
    {
        $this->toMaintenance();
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displayEmptytrash()
    {
        $this->toMaintenance();
    }

    /**
     * @return void
     * @since 5.5.0
     */
    public function displayNormalizenames()
    {
        $this->toMaintenance();
    }

    /**
     * @since Kunena
     * @throws Exception
     */
    public function displaySubscriptions()
    {
        $id = $this->app->input->get('id', 0, 'int');

        $topic           = KunenaForumTopicHelper::get($id);
        $acl             = KunenaAccess::getInstance();
        $cat_subscribers = $acl->loadSubscribers($topic, KunenaAccess::CATEGORY_SUBSCRIPTION);

        $this->cat_subscribers_users = KunenaUserHelper::loadUsers($cat_subscribers);

        $topic_subscribers             = $acl->loadSubscribers($topic, KunenaAccess::TOPIC_SUBSCRIPTION);
        $this->topic_subscribers_users = KunenaUserHelper::loadUsers($topic_subscribers);

        $this->cat_topic_subscribers = $acl->getSubscribers($topic->getCategory()->id, $id, KunenaAccess::CATEGORY_SUBSCRIPTION | KunenaAccess::TOPIC_SUBSCRIPTION, 1, 1);

        $this->display();
    }

    /**
     * Tools > Sample Data
     *
     * @return void
     * @since 5.3.0 (JoomlaWorks Ltd.)
     */
    public function displaySampledata()
    {
        $this->sampleCounts    = KunenaSampledata::getCounts();
        $this->samplePlan      = KunenaSampledata::getPlan();
        $this->sampleInstalled = array_sum($this->sampleCounts) > 0;
        $this->canManage       = Factory::getUser()->authorise('core.admin', 'com_kunena');
        JToolbarHelper::title(Text::_('COM_KUNENA') . ': ' . Text::_('COM_KUNENA_SAMPLEDATA'), 'tools');
        JToolbarHelper::spacer();
        JToolbarHelper::cancel();
        $this->display();
    }

    /**
     * @since Kunena
     */
    public function displayMenu()
    {
        $this->legacy    = KunenaMenuFix::getLegacy();
        $this->invalid   = KunenaMenuFix::getInvalid();
        $this->conflicts = KunenaMenuFix::getConflicts();

        $this->setToolBarMenu();
        $this->display();
    }

    /**
     * @since Kunena
     */
    protected function setToolBarMenu()
    {
        JToolbarHelper::title(Text::_('COM_KUNENA'), 'tools');
        JToolbarHelper::spacer();

        // Get the toolbar object instance
        $bar = \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar');

        if (!empty($this->legacy)) {
            JToolbarHelper::custom('fixlegacy', 'edit.png', 'edit_f2.png', 'COM_KUNENA_A_MENU_TOOLBAR_FIXLEGACY', false);
        }

        if (version_compare(JVERSION, '4.0', '>')) {
            HTMLHelper::_('bootstrap.renderModal', 'trashmenuconfirmationModal');
        } else {
            HTMLHelper::_('bootstrap.modal', 'trashmenuconfirmationModal');
        }

        $title = Text::_('COM_KUNENA_VIEW_TOOLS_RESTOREMENU_CONFIRMATION_TRASH');
        $dhtml = "<button data-toggle=\"modal\" data-target=\"#trashmenuconfirmationModal\" class=\"btn btn-small\">
					<i class=\"icon-apply\" title=\"$title\"> </i>
						$title</button>";
        $bar->appendButton('Custom', $dhtml, 'batch');

        JToolbarHelper::spacer();
        JToolbarHelper::cancel();
        JToolbarHelper::spacer();
        $help_url = 'https://docs.kunena.org/en/manual/backend/tools/menu-manager';
        JToolbarHelper::help('COM_KUNENA', false, $help_url);
    }

    /**
     * @since Kunena
     */
    public function displayDiagnostics()
    {
        $this->setToolBarDiagnostics();
        $this->display();
    }

    /**
     * @since Kunena
     */
    protected function setToolBarDiagnostics()
    {
        JToolbarHelper::title(Text::_('COM_KUNENA'), 'tools');
        JToolbarHelper::spacer();
        JToolbarHelper::cancel();
        JToolbarHelper::spacer();
        $help_url = 'https://docs.kunena.org/en/manual/backend/tools/diagnostics';
        JToolbarHelper::help('COM_KUNENA', false, $help_url);
    }

    /**
     * @since Kunena
     */
    public function displayUninstall()
    {
        $this->setToolBarUninstall();

        $login              = KunenaLogin::getInstance();
        $this->isTFAEnabled = $login->isTFAEnabled();

        $this->display();
    }

    /**
     * @since Kunena
     */
    protected function setToolBarUninstall()
    {
        JToolbarHelper::title(Text::_('COM_KUNENA'), 'tools');
        JToolbarHelper::spacer();
        JToolbarHelper::cancel();
        JToolbarHelper::spacer();
        $help_url = 'https://docs.kunena.org/en/manual/backend/tools/uninstall-kunena';
        JToolbarHelper::help('COM_KUNENA', false, $help_url);
    }

    /**
     * @since Kunena
     */
    public function displayReport()
    {
        $this->systemreport           = $this->get('SystemReport');
        $this->systemreport_anonymous = $this->get('SystemReportAnonymous');
        $this->setToolBarReport();
        $this->display();
    }

    /**
     * @since Kunena
     */
    protected function setToolBarReport()
    {
        JToolbarHelper::title(Text::_('COM_KUNENA'), 'help');
        JToolbarHelper::spacer();
        JToolbarHelper::cancel();
        JToolbarHelper::spacer();
        $help_url = 'https://docs.kunena.org/en/faq/configuration-report';
        JToolbarHelper::help('COM_KUNENA', false, $help_url);
    }
}

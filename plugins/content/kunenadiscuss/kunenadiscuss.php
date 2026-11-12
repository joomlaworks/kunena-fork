<?php

/**
 * Kunena Discuss Plugin
 *
 * @package       Kunena.plg_content_kunenadiscuss
 *
 * @copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die('');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Mail\MailHelper;
use Joomla\String\StringHelper;
use Joomla\Utilities\IpHelper;

/**
 * Class plgContentKunenaDiscuss
 * @since Kunena
 */
#[\AllowDynamicProperties]
class plgContentKunenaDiscuss extends CMSPlugin
{
    /**
     * Associative array to hold results of the plugin.
     *
     * @var array
     * @since Kunena
     *
     */
    protected static $plgDisplay = array();

    /**
     * @var boolean
     * @since Kunena
     */
    protected static $includedCss = false;

    /**
     * @var boolean
     * @since Kunena
     *
     */
    protected static $inevent = false;

    /**
     * @var JApplication
     * @since Kunena
     *
     */
    public $app = null;

    /**
     * @var JDatabaseDriver
     * @since Kunena
     *
     */
    public $db = null;

    /**
     * @var JUser
     * @since Kunena
     *
     */
    public $user = null;

    /**
     * @var KunenaConfig
     * @since Kunena
     *
     */
    public $config = null;

    /**
     * @param   object $subject
     * @param   array  $params
     *
     * @throws Exception
     * @since Kunena
     */
    public function __construct(&$subject, $params)
    {
        $this->app = Factory::getApplication();

        // Do not register plug-in in administration.
        if ($this->app->isAdmin()) {
            return;
        }

        // If scope isn't articles or Kunena, do not register plug-in.
        if ($this->app->scope != 'com_content' && $this->app->scope != 'com_kunena') {
            return;
        }

        // Kunena detection and version check
        $minKunenaVersion = '5.0';

        if (!class_exists('KunenaForum') || !KunenaForum::isCompatible($minKunenaVersion)) {
            $this->loadLanguage();
            $this->app->enqueueMessage(Text::sprintf('PLG_KUNENADISCUSS_DEPENDENCY_FAIL', $minKunenaVersion));

            return;
        }

        // Kunena online check
        if (!KunenaForum::enabled()) {
            return;
        }

        // Initialize variables
        $this->user   = KunenaFactory::getUser();
        $this->config = KunenaFactory::getConfig();

        // Initialize plugin
        parent::__construct($subject, $params);

        $this->debug("Constructor called in {$this->app->scope}");
    }

    /**
     * @param       $msg
     * @param   int $fatal
     *
     * @since Kunena
     *
     */
    protected function debug($msg, $fatal = 0)
    {
        // Print out debug info!
        $debug = $this->params->get('show_debug', false);

        // Joomla Id's of Users who can see debug info
        $debugUsers = $this->params->get('show_debug_userids', '');

        // Debug output only for the listed users (all users only when the list is empty, as before)
        if (!$debug || !$this->user || ($debugUsers && !in_array((int) $this->user->userid, array_map('intval', explode(',', $debugUsers)), true))) {
            return;
        }

        $msg = htmlspecialchars((string) $msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($fatal) {
            echo "<br /><span class=\"kdb-fatal\">[KunenaDiscuss FATAL: $msg ]</span>";
        } else {
            echo "<br />[KunenaDiscuss debug: $msg ]";
        }
    }

    /**
     * Before display content method.
     *
     * Method is called by the view and the results are imploded and displayed in a placeholder.
     *
     * @param   string  $context    The context for the content passed to the plugin.
     * @param   object  $article    The content object.  Note $article->text is also available
     * @param   object  $params     The content params
     * @param   integer $limitstart The 'page' number
     *
     * @return  string
     * @throws Exception
     * @since Kunena
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $limitstart = 0)
    {
        static $loaded = false;

        // Initialize plug-in during the first run.
        if (!$loaded) {
            $loaded = true;

            // Load language files and setup Kunena libraries.
            $this->loadLanguage();
            KunenaForum::setup();
            KunenaFactory::loadLanguage();

            // Create Kunena Discuss table if it doesn't exist.
            $this->createTable();
        }

        // Make sure that event gets only called once.
        if (self::$inevent) {
            return '';
        }

        self::$inevent = true;
        $this->prepare($context, $article, $params);
        self::$inevent = false;

        return '';
    }

    /**
     *
     *
     * @since version
     * @throws Exception
     */
    protected function createTable()
    {
        static $checked = false;

        if ($checked) {
            return true;
        }

        $checked = true;

        $this->debug('createTable: Check if plugin table exists.');

        // 5.3.0: portable table check (SHOW TABLES does not exist on PostgreSQL).
        $db     = Factory::getDbo();
        $prefix = $db->getPrefix();

        try {
            $tables = array_map('strtolower', (array) $db->getTableList());
        } catch (RuntimeException $e) {
            KunenaError::displayDatabaseError($e);

            return false;
        }

        if (in_array(strtolower($prefix . 'kunenadiscuss'), $tables, true)) {
            return true;
        }

        // CREATE TABLE IF NOT EXISTS with the types of each database (no engine/charset outside MySQL).
        $table = $db->quoteName('#__kunenadiscuss');
        $query = KunenaDatabaseHelper::choose(
            array(
                'mysql'      => "CREATE TABLE IF NOT EXISTS {$table} (
                    `content_id` int(11) NOT NULL default '0',
                    `thread_id` int(11) NOT NULL default '0',
                    PRIMARY KEY (`content_id`)
                )",
                'postgresql' => "CREATE TABLE IF NOT EXISTS {$table} (
                    {$db->quoteName('content_id')} integer NOT NULL DEFAULT 0,
                    {$db->quoteName('thread_id')} integer NOT NULL DEFAULT 0,
                    PRIMARY KEY ({$db->quoteName('content_id')})
                )",
            ),
            $db
        );
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            KunenaError::displayDatabaseError($e);

            return false;
        }

        $this->debug("Created #__kunenadiscuss cross reference table.");

        // Migrate data from the old FireBoard discussbot (FireBoard only ever ran on MySQL).
        if (KunenaDatabaseHelper::isMysql($db) && in_array(strtolower($prefix . 'fb_discussbot'), $tables, true)) {
            $db->setQuery(
                "REPLACE INTO `#__kunenadiscuss`
                SELECT `content_id` , `thread_id`
                FROM `#__fb_discussbot`"
            );

            try {
                $db->execute();
            } catch (RuntimeException $e) {
                KunenaError::displayDatabaseError($e);

                return false;
            }

            $this->debug("Migrated old data.");
        }

        return true;
    }

    /**
     * @param $context
     * @param $article
     * @param $params
     *
     * @throws Exception
     * @since Kunena
     */
    protected function prepare($context, &$article, &$params)
    {
        if (!isset($article->state) || $article->state != 1) {
            $this->debug("onPrepareContent: Article not published");

            return;
        }

        // Security: never show, create or reply to a discussion of an article the current user cannot view (access
        // level of the article or its category, scheduled/expired article, e.g. listed by show_noauth or seen by an editor).
        if (empty($article->id) || !$this->isArticleVisible((int) $article->id)) {
            $this->debug("onPrepareContent: Article not visible to the current user");

            return;
        }

        // Only proceed if this event is not originated by Kunena itself or we run the danger of an event recursion
        $ksource = '';

        if ($params instanceof Registry) {
            $ksource = $params->get('ksource', '');
        }

        if ($ksource != 'kunena') {
            $customTopics = $this->params->get('custom_topics', 1);

            $articleCategory = (isset($article->catid) ? $article->catid : 0);
            $isStaticContent = !$articleCategory;
            $articleCategory = (int) $articleCategory;

            if ($isStaticContent) {
                $kunenaCategory = false;
            } else {
                $kunenaCategory = $this->getForumCategory($articleCategory);

                if (!$kunenaCategory) {
                    if (!$customTopics) {
                        $this->debug("onPrepareContent: Allowing only Custom Topics");
                    }
                }
            }

            $kunenaTopic = false;
            $regex       = '/{kunena_discuss:(\d+?)}/s';

            if (Factory::getApplication()->input->getCmd('tmpl', '') == 'component' || Factory::getApplication()->input->getBool('print')
                || Factory::getApplication()->input->getCmd('format', 'html') != 'html' || (isset($article->state) && !$article->state)
                || empty($article->id) || $this->app->scope == 'com_kunena'
            ) {
                $this->debug("onPrepareContent: Not allowed - removing tags.");

                if (isset($article->text)) {
                    $article->text = preg_replace($regex, '', $article->text);
                }

                if (isset($article->introtext)) {
                    $article->introtext = preg_replace($regex, '', $article->introtext);
                }

                if (isset($article->fulltext)) {
                    $article->fulltext = preg_replace($regex, '', $article->fulltext);
                }
            }

            $view        = Factory::getApplication()->input->getCmd('view');
            $layout      = Factory::getApplication()->input->getCmd('layout');
            $isBlogPage  = ($view == 'section' || $view == 'category') && $layout == 'blog';
            $isFrontPage = $view == 'frontpage' || $view == 'featured';
            $isArticle   = $view == 'article';

            if ($isBlogPage) {
                $this->debug("onPrepareContent: we are in blog page.");
                $show = $this->params->get('show_blog_page', 2);
            } elseif ($isFrontPage) {
                $this->debug("onPrepareContent: we are in front page.");
                $show = $this->params->get('show_front_page', 2);
            } elseif ($isArticle) {
                $this->debug("onPrepareContent: we are viewing an article.");
                $show = $this->params->get('show_article_pages', 2);
            } else {
                $this->debug("onPrepareContent: we are in {$view}/{$layout} page.");
                $show = $this->params->get('show_other_pages', 2);
            }

            if (!$show || isset(self::$plgDisplay [$article->id])) {
                $this->debug("onPrepareContent: Configured to show nothing");

                if (isset($article->text)) {
                    $article->text = preg_replace($regex, '', $article->text);
                }

                if (isset($article->introtext)) {
                    $article->introtext = preg_replace($regex, '', $article->introtext);
                }

                if (isset($article->fulltext)) {
                    $article->fulltext = preg_replace($regex, '', $article->fulltext);
                }

                return;
            }

            $this->debug("onPrepareContent: Article {$article->id}");

            if (!$customTopics) {
                $this->debug("onPrepareContent: Custom Topics disabled");
            } else {
                // Get fulltext from frontpage articles (tag can be inside fulltext)
                if ($isFrontPage) {
                    $db    = Factory::getDBO();
                    $query = $db->getQuery(true);
                    $query->select($db->quoteName('fulltext'));
                    $query->from('#__content');
                    $query->where('id = ' . (int) $article->id);
                    $db->setQuery($query);

                    try {
                        $fulltext = $db->loadResult();
                    } catch (RuntimeException $e) {
                        KunenaError::displayDatabaseError($e);

                        return false;
                    }

                    $text = $article->introtext . ' ' . $fulltext;
                } else {
                    if (isset($article->text)) {
                        $text = $article->text;
                    } else {
                        $text = array();

                        if (isset($article->introtext)) {
                            $text [] = $article->introtext;
                        }

                        if (isset($article->fulltext)) {
                            $text [] = $article->fulltext;
                        }

                        $text = implode("\n\n", $text);
                    }
                }

                $matches = array();

                if (preg_match($regex, $text, $matches)) {
                    $kunenaTopic = intval($matches [1]);

                    if (isset($article->text)) {
                        $article->text = preg_replace("/{kunena_discuss:$kunenaTopic}/", '', $article->text, 1);
                    }

                    if (isset($article->introtext)) {
                        $article->introtext = preg_replace("/{kunena_discuss:$kunenaTopic}/", '', $article->introtext, 1);
                    }

                    if (isset($article->fulltext)) {
                        $article->fulltext = preg_replace("/{kunena_discuss:$kunenaTopic}/", '', $article->fulltext, 1);
                    }

                    if ($kunenaTopic == 0) {
                        $this->debug("onPrepareContent: Searched for {kunena_discuss:#}: Discussion of this article has been disabled.");

                        return;
                    }
                }

                $this->debug("onPrepareContent: Searched for {kunena_discuss:#}: Custom Topic "
                    . ($kunenaTopic ? "{$kunenaTopic} found." : "not found."));
            }

            if ($kunenaCategory || $kunenaTopic) {
                self::$plgDisplay [$article->id] = $this->showPlugin($kunenaCategory, $kunenaTopic, $article, $show == 1);
            }
        }
    }

    /**
     * Whether the article is published and the current user (or, with $public, a visitor) may view it.
     *
     * @param   int  $id     Article id
     * @param   bool $public Check guest access instead of the current user's
     *
     * @return boolean
     * @since 5.3.0
     */
    protected function isArticleVisible($id, $public = false)
    {
        static $articles = array();

        if (!array_key_exists($id, $articles)) {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('a.state, a.access, a.publish_up, a.publish_down, a.catid, c.published AS cat_published, c.access AS cat_access')
                ->from('#__content AS a')
                ->leftJoin('#__categories AS c ON c.id = a.catid')
                ->where('a.id = ' . (int) $id);
            $db->setQuery($query);

            try {
                $articles[$id] = $db->loadObject();
            } catch (RuntimeException $e) {
                $articles[$id] = null;
            }
        }

        $article = $articles[$id];

        if (!$article) {
            return false;
        }

        $db       = Factory::getDbo();
        $nowDate  = Factory::getDate()->toSql();
        $nullDate = $db->getNullDate();

        if ((int) $article->state !== 1
            || (!empty($article->publish_up) && $article->publish_up != $nullDate && $article->publish_up > $nowDate)
            || (!empty($article->publish_down) && $article->publish_down != $nullDate && $article->publish_down < $nowDate)
            || ($article->catid && (int) $article->cat_published !== 1)
        ) {
            return false;
        }

        $levels = $public ? Factory::getUser(0)->getAuthorisedViewLevels() : Factory::getUser()->getAuthorisedViewLevels();

        return in_array((int) $article->access, array_map('intval', $levels), true)
            && (!$article->catid || in_array((int) $article->cat_access, array_map('intval', $levels), true));
    }

    /**
     * ***************************************************************************
     * Output
     *****************************************************************************/

    /**
     * ***************************************************************************
     * Permission checks
     *****************************************************************************
     * @since Kunena
     *
     * @param $catid
     *
     * @return bool|int
     */
    protected function getForumCategory($catid)
    {
        // Default Kunena category to put new topics into
        $default = intval($this->params->get('default_category', 0));

        // Category pairs will be always allowed
        $categoryPairs = explode(';', $this->params->get('category_mapping', ''));
        $categoryMap   = array();

        foreach ($categoryPairs as $pair) {
            $pair  = explode(',', $pair);
            $key   = isset($pair [0]) ? intval($pair [0]) : 0;
            $value = isset($pair [1]) ? intval($pair [1]) : 0;

            if ($key > 0) {
                $categoryMap [$key] = $value;
            }
        }

        // Limit plugin to the following content categories
        $allowCategories = explode(',', $this->params->get('allow_categories', ''));

        // Exclude the plugin from the following categories
        $denyCategories = explode(',', $this->params->get('deny_categories', ''));

        if (!is_numeric($catid) || intval($catid) == 0) {
            $this->debug("onPrepareContent.Deny: Category {$catid} is not valid");

            return false;
        }

        $db    = Factory::getDBO();
        $query = $db->getQuery(true);
        $query->select($db->quoteName('parent_id'));
        $query->from('#__categories');
        $query->where('id = ' . (int) $catid);
        $db->setQuery($query);

        try {
            $parent_catid = $db->loadResult();
        } catch (Exception $e) {
            $this->debug("onPrepareContent.Parent IDs: Error executing query - " . $e->getMessage());
            $parent_catid = 0;
        }

        // Parent ID of the article's category
        $this->debug("onPrepareContent.Parent category ID is: " . $parent_catid);

        // Let's check the mapping
        if (!empty($categoryMap)) {
            if (isset($categoryMap[$catid])) {
                $forumcatid = intval($categoryMap [$catid]);

                if (!$forumcatid) {
                    $this->debug("onPrepareContent.Deny: Category {$catid} was disabled in the category map.");

                    return false;
                }

                $this->debug("onPrepareContent.Allow: Category {$catid} is in the category map using Kunena category {$forumcatid}");

                return $forumcatid;
            } else {
                if (!empty($parent_catid) && isset($categoryMap[$parent_catid])) {
                    $forumcatid = intval($categoryMap[$parent_catid]);
                    $msg        = "onPrepareContent.Allow: "
                        . "Parent category {$parent_catid} of the article category {$catid} is in the category map using Kunena category {$forumcatid}";
                    $this->debug($msg);

                    return $forumcatid;
                }
            }
        }

        if (!$default) {
            $this->debug("onPrepareContent.Deny: There is no default Kunena category");

            return false;
        }

        if (in_array('0', $allowCategories) || in_array($catid, $allowCategories)) {
            $this->debug("onPrepareContent.Allow: Category {$catid} was listed in allow list and is using default Kunena category {$default}");

            return $default;
        }

        if (in_array('0', $denyCategories) || in_array($catid, $denyCategories)) {
            $this->debug("onPrepareContent.Deny: Category {$catid} was listed in deny list");

            return false;
        }

        $this->debug("onPrepareContent.Allow: Category {$catid} is using default Kunena category {$default}");

        return $default;
    }

    /**
     * @param   int    $catid
     * @param   int    $topic_id
     * @param   object $row
     * @param   bool   $linkOnly
     *
     * @return mixed|string
     * @throws Exception
     * @since Kunena
     */
    protected function showPlugin($catid, $topic_id, &$row, $linkOnly)
    {
        // Show a simple form to allow posting to forum from the plugin
        $plgShowForm = $this->params->get('form', 1);

        // Default is to put QuickPost at the very bottom.
        $formLocation = $this->params->get('form_location', 0);

        // Don't repeat the CSS for each instance of this plugin in a page!
        if (!self::$includedCss) {
            $doc = Factory::getDocument();
            $doc->addStyleSheet(Uri::root(true) . "/plugins/content/kunenadiscuss/css/discuss.css");

            $plugin       = PluginHelper::getPlugin('content', 'kunenadiscuss');
            $pluginParams = new Registry($plugin->params);
            $bootstrap    = $pluginParams->get('bootstrap');

            if ($bootstrap != 'B3') {
                $doc->addStyleSheet(Uri::root(true) . "/plugins/content/kunenadiscuss/css/discussb2.css");
            }

            self::$includedCss = true;
        }

        $result = false;

        // Find cross reference and the real topic
        $db    = Factory::getDBO();
        $query = $db->getQuery(true);
        $query->select($db->quoteName('thread_id'));
        $query->from('#__kunenadiscuss');
        $query->where($db->quoteName('content_id') . ' = ' . (int) $row->id);
        $db->setQuery($query);

        try {
            $result = $db->loadResult();
        } catch (RuntimeException $e) {
            KunenaError::displayDatabaseError($e);

            return false;
        }

        if ($topic_id) {
            // Custom topic found
            $this->debug("showPlugin: Loading Custom Topic {$topic_id}");
            $id = $topic_id;
        } elseif ($result) {
            // Reference found
            $this->debug("showPlugin: Loading Stored Topic {$result}");
            $id = $result;
        } else {
            // No topic exists
            $this->debug("showPlugin: No topic found");
            $id = 0;
        }

        $topic = KunenaForumTopicHelper::get($id);

        // If topic has been moved, find the real topic (bounded: a corrupted chain must not loop forever)
        for ($hops = 0; $topic->moved_id && $hops < 10; $hops++) {
            $this->debug("showPlugin: Topic {$topic->id} has been moved to {$topic->moved_id}");
            $topic = KunenaForumTopicHelper::get($topic->moved_id);
        }

        if ($result) {
            if (!$topic->exists()) {
                $this->debug("showPlugin: Topic does not exist, removing reference to {$result}");
                $this->deleteReference($row);
            } elseif ($topic->id != $id) {
                $this->debug("showPlugin: Topic has been moved or changed, updating reference to {$topic->id}");
                $this->updateReference($row, $topic->id);
            }
        } elseif ($topic_id && $topic->exists()) {
            $this->debug("showPlugin: First hit to Custom Topic, created reference to topic {$topic_id}");
            $this->createReference($row, $topic_id);
        }

        // Initialise some variables
        $subject = $row->title;

        if (isset($row->publish_up) && $row->publish_up != '1000-01-01 00:00:00') {
            // Take start publishing date
            $published = Factory::getDate($row->publish_up)->toUnix();
        } else {
            // Or created date if publish_up is empty
            $published = Factory::getDate($row->created)->toUnix();
        }

        $now = Factory::getDate()->toUnix();

        if ($topic->exists()) {
            // If current user doesn't have authorisation to read existing topic, we are done
            if ($id && !$topic->isAuthorised('read')) {
                $this->debug("showPlugin: Topic said {$topic->getError()}");

                return '';
            }

            $category = $topic->getCategory();
        } else {
            $this->debug("showPlugin: Let's see what we can do..");

            // If current user doesn't have authorisation to read category, we are done
            $category = KunenaForumCategoryHelper::get($catid);

            if (!$category->isAuthorised('read')) {
                $this->debug("showPlugin: Category {$catid} said {$category->getError()}");

                return '';
            }

            $create = $this->params->get('create', 0);

            // Weeks in seconds
            $createTime = $this->params->get('create_time', 0) * 604800;

            if ($createTime && $published + $createTime < $now) {
                $this->debug("showPlugin: Topic creation time expired, cannot start new discussion anymore");

                return '';
            }

            if ($create) {
                $this->debug("showPlugin: First hit, created new topic {$topic_id} into forum");
                $topic = $this->createTopic($row, $category, $subject);

                if ($topic === false) {
                    return '';
                }
            }
        }

        // Do we allow answers into the topic? Weeks in seconds or 0 (forever)
        $closeTime = $this->params->get('close_time', 0) * 604800;

        if ($closeTime && $topic->exists()) {
            $closeReason = $this->params->get('close_reason', 0);

            if ($closeReason) {
                $this->debug("showPlugin: Close time by last post");
                $closeTime += $topic->last_post_time;
            } else {
                $this->debug("showPlugin: Close time by topic creation");
                $closeTime += $topic->first_post_time;
            }
        } else {
            // Topic has not yet been created or will kept open forever
            $closeTime = $now + 1;
        }

        $linktopic = '';

        if ($topic->exists() && $linkOnly) {
            $this->debug("showPlugin: Displaying only link to the topic");
            $linktitle = Text::sprintf('PLG_KUNENADISCUSS_DISCUSS_ON_FORUMS', $topic->getReplies());

            return HTMLHelper::_('kunenaforum.link', $topic->getUri($category), $linktitle, $linktitle);
        } elseif ($topic->exists() && !$plgShowForm) {
            $this->debug("showPlugin: Displaying link to the topic because the form is disabled");
            $linktitle = Text::sprintf('PLG_KUNENADISCUSS_DISCUSS_ON_FORUMS', $topic->getReplies());
            $linktopic = HTMLHelper::_('kunenaforum.link', $topic->getUri($category), $linktitle, $linktitle);
        } elseif (!$topic->exists() && !$plgShowForm) {
            $linktopic = Text::_('PLG_KUNENADISCUSS_NEW_TOPIC_NOT_CREATED');
        }

        // ************************************************************************
        // Process the QuickPost form

        $quickPost = '';
        $canPost   = $this->canPost($category, $topic);

        if ($canPost && $plgShowForm && (!$closeTime || $closeTime >= $now)) {
            if (Factory::getUser()->get('guest')) {
                $this->debug("showPlugin: Guest can post: this feature doesn't work well if Joomla caching or Cache Plugin is enabled!");
            }

            if (Factory::getApplication()->input->post->getInt('kdiscussContentId', -1) === (int) $row->id) {
                $this->debug("showPlugin: Reply topic!");
                $reply      = $this->replyTopic($row, $category, $topic, $subject);
                $quickPost .= $reply === false ? $this->showForm($row, $category, $topic, $subject) : $reply;
            } else {
                $this->debug("showPlugin: Displaying form");
                $quickPost .= $this->showForm($row, $category, $topic, $subject);
            }
        }

        // This will be used all the way through to tell users how many posts are in the forum.
        $content = $this->showTopic($category, $topic, $linktopic);

        if (!$content && !$quickPost) {
            return $linktopic;
        }

        if ($formLocation) {
            $content = '<div class="kunenadiscuss">' . $content . '<br />' . $quickPost . '</div>';
        } else {
            $content = '<div class="kunenadiscuss">' . $quickPost . "<br />" . $content . '</div>';
        }

        return $content;
    }

    /**
     * @param   object $row
     *
     * @return bool
     * @throws Exception
     * @since Kunena
     */
    protected function deleteReference($row)
    {
        $this->debug("deleteReference: Delete");

        $db    = Factory::getDBO();
        $query = $db->getQuery(true);
        $query->delete('#__kunenadiscuss');
        $query->where($db->quoteName('content_id') . ' = ' . (int) $row->id);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            $this->debug("deleteReference: Delete error " . $e->getMessage());

            KunenaError::displayDatabaseError($e);

            return false;
        }

        return true;
    }

    /**
     * @param   object $row
     * @param   int    $topic_id
     *
     * @return bool
     * @throws Exception
     * @since Kunena
     */
    protected function updateReference($row, $topic_id)
    {
        $this->debug("updateReference: Update");

        $db    = Factory::getDBO();
        $query = $db->getQuery(true);
        $query->update('#__kunenadiscuss');
        $query->set($db->quoteName('thread_id') . ' = ' . (int) $topic_id);
        $query->where($db->quoteName('content_id') . ' = ' . (int) $row->id);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            $this->debug("updateReference: Update error " . $e->getMessage());

            KunenaError::displayDatabaseError($e);

            return false;
        }

        return true;
    }

    /**
     * ***************************************************************************
     * Create and reply to topic
     *****************************************************************************
     * @since Kunena
     *
     * @param $row
     * @param $topic_id
     *
     * @return bool
     * @throws Exception
     */
    protected function createReference($row, $topic_id)
    {
        $this->debug("createReference: create");

        $db    = Factory::getDBO();
        $query = $db->getQuery(true);
        $query->insert('#__kunenadiscuss');
        $query->columns(array($db->quoteName('content_id'), $db->quoteName('thread_id')));
        $query->values((int) $row->id . ', ' . (int) $topic_id);
        $db->setQuery($query);

        try {
            $db->execute();
        } catch (RuntimeException $e) {
            // Another request created the reference first (primary key): keep that one. Deleting it (as before) made
            // the next page view create yet another forum topic for the article.
            $this->debug("createReference: Error:" . $e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @param                       $row
     * @param   KunenaForumCategory $category
     * @param                       $subject
     *
     * @return boolean|KunenaForumTopic
     * @throws Exception
     * @since Kunena
     */
    protected function createTopic($row, KunenaForumCategory $category, $subject)
    {
        if (!$category->exists()) {
            $this->debug("showPlugin: Topic creation failed: forum category doesn't exist!");

            return false;
        }

        // Security: only into a published forum (not a section) and only for an article the visitor may view.
        if ($category->isSection() || (int) $category->published !== 1 || empty($row->id) || !$this->isArticleVisible((int) $row->id)) {
            $this->debug("showPlugin: Topic creation failed: forum category not usable or article not visible");

            return false;
        }

        $this->debug("showPlugin: Create topic!");

        // The snippet copies article text into the forum: only for articles that everybody may read, so that text of
        // a restricted article is not disclosed in a forum category with wider access.
        $add_snippet = $this->params->get('add_article_snippet') && $this->isArticleVisible((int) $row->id, true);
        $fulltext    = isset($row->fulltext) ? (string) $row->fulltext : '';
        $introtext   = isset($row->introtext) ? (string) $row->introtext : '';
        $textwords   = implode(' ', array_slice(explode(' ', $fulltext), 0, 10));

        if (trim($textwords) === '') {
            $textwords = implode(' ', array_slice(explode(' ', $introtext), 0, 10));
        }

        $snippet = strip_tags($textwords) . "..." . "\n\n";

        $type = $this->params->get('bbcode');

        switch ($type) {
            case 'full':
            case 'intro':
            case 'link':
                {
                    if ($add_snippet) {
                        $contents = $snippet . "[article={$type}]{$row->id}[/article]";
                    } else {
                        $contents = "[article={$type}]{$row->id}[/article]";
                    }
                }
                break;
            default:
                {
                    if ($add_snippet) {
                        $contents = $snippet . "[article]{$row->id}[/article]";
                    } else {
                        $contents = "[article]{$row->id}[/article]";
                    }
                }
        }

        // Save the ID for later use (an unknown owner falls back to the article author, never to the visitor)
        $topic_owner = $this->params->get('topic_owner', isset($row->created_by) ? $row->created_by : 0);
        $user        = KunenaUserHelper::get($topic_owner !== null && $topic_owner !== '' ? $topic_owner : 0);

        if (!$user->exists() && !empty($row->created_by)) {
            $user = KunenaUserHelper::get((int) $row->created_by);
        }

        $topic_owner = (int) $user->userid;

        // Get real email, we need to pass email of the topic starter (robot) when 'Require E-mail' option is enabled
        $email      = $user->email;
        $params     = array(
            'email'   => $email,
            'subject' => $subject,
            'message' => $contents,
        );
        $safefields = array(
            'category_id' => intval($category->id)
        );
        list($topic, $message) = $category->newTopic($params, $topic_owner, $safefields);

        // Set time of message published by the plugin in the Unix timestamp format, start publishing date of the article
        if (isset($row->publish_up) && $row->publish_up != '1000-01-01 00:00:00') {
            $message->time = Factory::getDate($row->publish_up)->toUnix();
        } else {
            if (isset($row->created) && $row->created != '1000-01-01 00:00:00') {
                // Created date of the article
                $message->time = Factory::getDate($row->created)->toUnix();
            } else {
                // Current date and time
                $message->time = Factory::getDate()->toUnix();
            }
        }

        $success = $message->save();

        if (!$success) {
            $this->debug("showPlugin: Error:" . $message->getError());

            $this->app->enqueueMessage($message->getError(), 'error');

            return false;
        }

        // Create a reference
        $this->createReference($row, $topic->id);

        return $topic;
    }

    /**
     * @param   KunenaForumCategory $category
     * @param   KunenaForumTopic    $topic
     *
     * @return boolean
     * @throws Exception
     * @since Kunena
     */
    protected function canPost(KunenaForumCategory $category, KunenaForumTopic $topic)
    {
        if ($topic->exists()) {
            return $topic->isAuthorised('reply');
        } else {
            return $category->isAuthorised('topic.reply');
        }
    }

    /**
     * @param                       $row
     * @param   KunenaForumCategory $category
     * @param   KunenaForumTopic    $topic
     * @param                       $subject
     *
     * @return boolean|string
     * @throws Exception
     * @since Kunena
     */
    protected function replyTopic($row, KunenaForumCategory $category, KunenaForumTopic $topic, $subject)
    {
        KunenaFactory::loadLanguage('com_kunena.controllers');

        if (!Session::checkToken('post')) {
            $this->debug("showPlugin: Token error");

            $this->app->enqueueMessage(Text::_('COM_KUNENA_ERROR_TOKEN'), 'error');

            return false;
        }

        // 5.3.0 security: every check runs before anything is written. The forum topic used to be created first, so
        // a failed CAPTCHA (or any other failed check) still left a new, empty forum thread behind.

        // The user must be allowed to post here (checked again: never rely on the caller only)
        if (!$this->canPost($category, $topic) || !$this->isArticleVisible((int) $row->id)) {
            $this->redirectBack(Text::_('COM_KUNENA_NO_ACCESS'), 'error');
        }

        $input = $this->app->input;
        $email = null;
        $name  = null;

        if (!$this->user->exists()) {
            // Guests: name and e-mail from the form (a name of a registered user becomes "Anonymous" in
            // KunenaForumMessage::check(), so registered users cannot be impersonated).
            $name  = StringHelper::substr(trim((string) $input->post->getString('name', '')), 0, 255);
            $email = StringHelper::substr(trim((string) $input->post->getString('email', '')), 0, 255);

            if ($email !== '' && !MailHelper::isEmailAddress($email)) {
                $this->redirectBack(Text::_('COM_KUNENA_LIB_MESSAGE_ERROR_EMAIL_INVALID'), 'error');
            }

            if ($email === '' && $this->config->askemail) {
                $this->redirectBack(Text::_('COM_KUNENA_LIB_MESSAGE_ERROR_EMAIL_EMPTY'), 'error');
            }
        }

        $text = (string) $input->post->getString('message', '');

        // Links and images of new users are removed, as in the forum
        if ($this->user->checkUserAllowedLinksImages()) {
            $text = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/su', '', $text);
            $text = preg_replace('/\[img=(.*?)\](.*?)\[\/img\]/su', '', $text);
            $text = preg_replace('/(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)(#?[\w \.-]*)(\??[\w \.-]*)(\=?[\w \.-]*)/i', '', $text);
        }

        // The message must have visible content
        $html = KunenaHtmlParser::parseBBCode($text);

        if (trim($text) === '' || (!preg_match('!(<img |<object |<iframe )!', $html) && trim(OutputFilter::cleanText($html)) === '')) {
            $this->redirectBack(Text::_('COM_KUNENA_LIB_TABLE_MESSAGES_ERROR_NO_MESSAGE'), 'error');
        }

        // Spam: maximum number of external links (Kunena configuration), as in the forum
        if (!$this->checkMaxLinks($html, $category)) {
            $this->redirectBack(Text::_('COM_KUNENA_TOPIC_SPAM_LINK_PROTECTION'), 'error');
        }

        // CAPTCHA (KunenaCaptcha: Joomla's Global Configuration, fails closed)
        if ($this->hasCaptcha() && !$this->verifyCaptcha()) {
            $this->redirectBack();
        }

        // Flood protection (Kunena configuration), by IP address and user account, as in the forum
        if ($this->config->floodprotection && !$this->user->isModerator($category)) {
            $timelimit = Factory::getDate()->toUnix() - (int) $this->config->floodprotection;
            $db        = Factory::getDbo();
            $where     = $db->quoteName('ip') . ' = ' . $db->quote((string) IpHelper::getIp());

            if ($this->user->userid) {
                $where = '(' . $where . ' OR ' . $db->quoteName('userid') . ' = ' . (int) $this->user->userid . ')';
            }

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_messages'))
                ->where($where)
                ->where($db->quoteName('time') . ' > ' . (int) $timelimit);
            $db->setQuery($query);

            try {
                $count = (int) $db->loadResult();
            } catch (RuntimeException $e) {
                KunenaError::displayDatabaseError($e);
                $count = 1;
            }

            if ($count) {
                $this->redirectBack(Text::sprintf('COM_KUNENA_POST_TOPIC_FLOOD', $this->config->floodprotection), 'error');
            }
        }

        // All checks passed: create the topic if it doesn't exist yet
        if (!$topic->exists()) {
            $this->debug("replyTopic: Create topic");

            $topic = $this->createTopic($row, $category, $subject);

            if (!$topic) {
                $this->redirectBack();
            }
        }

        $params = array(
            'subject' => $subject,
            'message' => $text,
        );

        if (!$this->user->exists()) {
            $params['name']  = $name;
            $params['email'] = $email;
        }

        $message = $topic->newReply($params);

        // Hold posts from guests / new users (Kunena configuration), as in the forum (never lowering a held state)
        if (!$message->hold && !$this->user->userid && $this->config->hold_guest_posts) {
            $message->hold = 1;
        }

        if (!$message->hold && $this->user->userid && !$this->user->isModerator($category) && $this->user->posts < $this->config->hold_newusers_posts) {
            $message->hold = 1;
        }

        if (!$this->config->iptracking) {
            $message->ip = '';
        }

        $success = $message->save();

        if (!$success) {
            $this->debug("replyTopic: can't save message: " . $message->getError());

            $this->redirectBack($message->getError(), 'error');
        }

        $message->sendNotification();

        if ($message->hold) {
            $result = Text::_('PLG_KUNENADISCUSS_PENDING_MODERATOR_APPROVAL');
        } else {
            $result = Text::_('PLG_KUNENADISCUSS_MESSAGE_POSTED');
        }

        $this->redirectBack($result);

        return '';
    }

    /**
     * Redirects back to the current page (a local URL only: path and query of the request, never its host).
     *
     * @param   string $msg  Message
     * @param   string $type Message type
     *
     * @return void
     * @since 5.3.0
     */
    protected function redirectBack($msg = '', $type = 'message')
    {
        $url = Uri::getInstance()->toString(array('path', 'query'));

        // A path starting with // or /\ would be a protocol-relative URL to another host.
        $url = '/' . ltrim((string) $url, '/\\');

        if ($msg !== '') {
            $this->app->enqueueMessage($msg, $type);
        }

        $this->app->redirect($url);
    }

    /**
     * Maximum number of external links in a message (Kunena configuration max_links), as in the forum.
     *
     * @param   string              $html     Message as HTML
     * @param   KunenaForumCategory $category Category
     *
     * @return boolean
     * @since 5.3.0
     */
    protected function checkMaxLinks($html, KunenaForumCategory $category)
    {
        if ($this->user->isAdmin() || $this->user->isModerator($category)) {
            return true;
        }

        preg_match_all('/<a\s[^>]*href="([^"]*)"[^>]*>/siU', $html, $matches);

        $count = 0;

        foreach ($matches[1] as $link) {
            $link = html_entity_decode($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            if (Uri::getInstance($link)->getHost() && !Uri::isInternal($link)) {
                $count++;
            }
        }

        return $count <= (int) $this->config->max_links;
    }

    /**
     * Check if the user will have captcha or not
     *
     * @return boolean
     * @throws Exception
     * @since Kunena
     */
    public function hasCaptcha()
    {
        return KunenaCaptcha::isRequired($this->user);
    }

    /**
     * Check if the captcha given is correct
     *
     * @return boolean
     * @throws Exception
     * @since Kunena
     */
    protected function verifyCaptcha()
    {
        // 5.3.0: the CAPTCHA selected in Joomla's Global Configuration, through Kunena's shared integration.
        // Fails closed (the old code accepted every post unless the first CAPTCHA plugin had reCAPTCHA-style keys).
        $error = null;

        if (KunenaCaptcha::verify($this->user, $error)) {
            return true;
        }

        $this->debug('replyTopic: CAPTCHA failed: ' . $error);
        $this->app->enqueueMessage($error, 'error');

        return false;
    }

    /**
     * @param                       $row
     * @param   KunenaForumCategory $category
     * @param   KunenaForumTopic    $topic
     * @param                       $subject
     *
     * @return string
     * @throws Exception
     * @since Kunena
     */
    protected function showForm($row, KunenaForumCategory $category, KunenaForumTopic $topic, $subject)
    {
        $canPost = $this->canPost($category, $topic);

        if (!$canPost) {
            if (!$this->user->exists()) {
                $this->debug("showForm: Public posting is not permitted, show login instead");
                $login        = KunenaLogin::getInstance();
                $loginlink    = $login->getLoginURL();
                $registerlink = $login->getRegistrationURL();
                $this->msg    = Text::sprintf('PLG_KUNENADISCUSS_LOGIN_OR_REGISTER', '"' . $loginlink . '"', '"' . $registerlink . '"');
            } else {
                $this->debug("showForm: Unfortunately you cannot discuss this item");
                $this->msg = Text::_('PLG_KUNENADISCUSS_NO_PERMISSION_TO_POST');
            }
        }

        // Security: values reflected from POST are escaped here (they were echoed raw into attributes: reflected XSS).
        // KunenaUser::getName() is already HTML-escaped.
        $input         = Factory::getApplication()->input;
        $this->open    = $this->params->get('quickpost_open', false);
        $this->name    = $this->user->exists() ? $this->user->getName()
            : htmlspecialchars((string) $input->post->getString('name', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->email   = $this->user->exists() ? ''
            : htmlspecialchars((string) $input->post->getString('email', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->message = htmlspecialchars((string) $input->post->getString('message', ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        ob_start();
        $this->debug("showForm: Rendering form");
        include __DIR__ . "/tmpl/form.php";
        $str = ob_get_contents();
        ob_end_clean();

        return $str;
    }

    /**
     * @param   KunenaForumCategory $category
     * @param   KunenaForumTopic    $topic
     * @param   string              $link_topic
     *
     * @return string
     * @throws Exception
     * @since Kunena
     */
    protected function showTopic(KunenaForumCategory $category, KunenaForumTopic $topic, $link_topic)
    {
        if (!$topic->exists()) {
            $this->debug("showTopic: No messages to render");

            return '';
        }

        $this->debug("showTopic: Rendering discussion");

        $article_id = $this->app->input->get('id');

        $ordering = $this->params->get('ordering', 1); // 0=ASC, 1=DESC
        $params   = array(
            'catid'            => $category->id,
            'id'               => $topic->id,
            'limitstart'       => (int) !$ordering,
            'limit'            => $this->params->get('limit', 25),
            'filter_order_Dir' => $ordering ? 'desc' : 'asc',
            'templatepath'     => __DIR__ . '/tmpl'
        );

        ob_start();
        KunenaForum::display('topic', 'default', null, $params);
        $str = ob_get_contents();
        ob_end_clean();

        // Set the correct article id back on the content page
        $this->app->input->set('id', $article_id);

        return $link_topic . $str;
    }

    /**
     * Display the captcha into the post form
     *
     * @since Kunena
     *
     */
    public function displayCaptcha()
    {
        return KunenaCaptcha::display('kunena_discuss_captcha_' . mt_rand(100, 999));
    }

    /**
     * After display content method.
     *
     * Method is called by the view and the results are imploded and displayed in a placeholder.
     *
     * @param   string  $context    The context for the content passed to the plugin.
     * @param   object  $article    The content object.  Note $article->text is also available
     * @param   object  $params     The content params
     * @param   integer $limitstart The 'page' number
     *
     * @return  string
     * @throws Exception
     * @since Kunena
     */
    public function onContentAfterDisplay($context, &$article, &$params, $limitstart = 0)
    {
        // Make sure that event gets only called once and there's something to display.
        if (self::$inevent || !isset($article->id) || !isset(self::$plgDisplay[$article->id])) {
            return '';
        }

        $this->ktemplate = KunenaFactory::getTemplate();
        if ($this->ktemplate->params->get('fontawesome')) {
            $doc = Factory::getDocument();
            $doc->addScript('https://use.fontawesome.com/releases/v5.6.1/js/all.js', array(), array('defer' => true));
        }

        $this->debug("onAfterDisplayContent: Returning content for article {$article->id}");

        $result = self::$plgDisplay[$article->id];
        $user   = Factory::getUser();

        if ($user->guest) {
            $login_public = $this->params->get('login_public', 0);

            if ($login_public) {
                $guestHtml = "<div class='kunenadiscuss kpublic'>";
                $guestHtml = $guestHtml . "<div class='kdiscuss-title login-discuss'>" . Text::_('PLG_KUNENADISCUSS_DISCUSS_THIS_ARTICLE') . "</div>";
                $guestHtml = $guestHtml . "<a class='klogin-to-discuss' rel='nofollow' href='" . htmlspecialchars((string) KunenaLogin::getInstance()->getLoginURL(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "' >" . Text::_('PLG_KUNENADISCUSS_LOG_IN_TO_COMMENT') . "</a>";
                $guestHtml = $guestHtml . "</div>";
                $result    = $guestHtml . $result;
            }
        }

        return $result;
    }
}

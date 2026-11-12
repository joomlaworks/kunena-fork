<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Seo
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Search engine and social sharing metadata of the forum pages: Google's structured data (JSON-LD) for discussions
 * (DiscussionForumPosting with its replies as Comment) and forum profiles (ProfilePage), and the OpenGraph and X (Twitter)
 * card tags. The display controllers call it from prepareDocument(); Kunena > Configuration > SEO & Feeds switches each
 * part on or off (for sites where an SEO extension writes these tags).
 *
 * Structured data describes only what a visitor sees: published posts of categories guests can read, the posts on
 * the current page.
 *
 * On Joomla 3.x UTD, whose plugin "System - Structured Data" writes these tags for every page (site, publisher,
 * breadcrumbs), Kunena hands its values to that plugin (onStructuredDataBeforeRender, called by the System - Kunena
 * plugin): one graph and one set of tags, with the plugin's own settings for everything Kunena doesn't describe.
 * Elsewhere (Joomla 3.9/3.10, or the plugin disabled) Kunena writes them itself.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaSeo
{
    /**
     * schema.org value for content written by an AI model (Baldrick's replies)
     *
     * @since 5.3.0
     */
    public const AI_SOURCE = 'https://schema.org/TrainedAlgorithmicMediaDigitalSource';

    /**
     * Longest description in the meta tags (characters)
     *
     * @since 5.3.0
     */
    public const DESCRIPTION_LENGTH = 200;

    /**
     * Values waiting for Joomla 3.x UTD's structured data plugin: opengraph and x (name => content), graph (nodes)
     *
     * @var    array
     * @since  5.3.0
     */
    protected static $pending = array('opengraph' => array(), 'x' => array(), 'graph' => array());

    /**
     * @return  boolean  Joomla 3.x UTD's "System - Structured Data" plugin writes the tags of the page
     *
     * @since 5.3.0
     */
    public static function viaPlatform()
    {
        return PluginHelper::isEnabled('system', 'structureddata');
    }

    /**
     * Hands Kunena's values to Joomla 3.x UTD's structured data plugin (its onStructuredDataBeforeRender event): they
     * replace the plugin's generic values for the forum pages and join its graph. A family the plugin won't write (turned
     * off there, or set by another extension) is written by Kunena itself.
     *
     * @param   string  $context  Page context (option.view)
     * @param   object  $data     opengraph, x, graph
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function render($context, $data)
    {
        if (strpos((string) $context, 'com_kunena') !== 0 || !is_object($data)) {
            return;
        }

        $pending         = static::$pending;
        static::$pending = array('opengraph' => array(), 'x' => array(), 'graph' => array());

        foreach (array('opengraph' => 'og:image', 'x' => 'twitter:image') as $family => $image) {
            if (!$pending[$family]) {
                continue;
            }

            if (empty($data->$family)) {
                static::writeTags($family, $pending[$family]);
                continue;
            }

            $tags = (array) $data->$family;

            // The plugin's image details belong to its image
            if (isset($pending[$family][$image])) {
                foreach (array_keys($tags) as $name) {
                    if (strpos((string) $name, $image . ':') === 0) {
                        unset($tags[$name]);
                    }
                }
            }

            $data->$family = array_merge($tags, $pending[$family]);
        }

        foreach ($pending['graph'] as $node) {
            if (empty($data->graph)) {
                static::writeJsonLd($node);
            } else {
                $data->graph[] = $node;
            }
        }
    }

    /**
     * @return  boolean  Structured data (JSON-LD) is output
     *
     * @since 5.3.0
     */
    public static function structuredDataEnabled()
    {
        return (bool) KunenaConfig::getInstance()->seo_structured_data && static::isHtml();
    }

    /**
     * @return  boolean  OpenGraph and X tags are output
     *
     * @since 5.3.0
     */
    public static function socialTagsEnabled()
    {
        return (bool) KunenaConfig::getInstance()->seo_social_tags && static::isHtml();
    }

    /**
     * @return  boolean  The page is an HTML document (not a feed or raw output)
     *
     * @since 5.3.0
     */
    protected static function isHtml()
    {
        return Factory::getDocument()->getType() === 'html';
    }

    /**
     * Whether guests can read a category: only those get structured data (search engines read as guests).
     *
     * @param   KunenaForumCategory  $category  Category
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isPublic(KunenaForumCategory $category)
    {
        return in_array((int) $category->id, KunenaForumCategoryHelper::getGuestReadable(), true);
    }

    /**
     * Thread page: DiscussionForumPosting (the first post, with the replies on this page as Comment) and the sharing
     * tags.
     *
     * @param   KunenaForumTopic     $topic       Thread
     * @param   KunenaForumCategory  $category    Category the thread is shown in
     * @param   KunenaForumMessage[] $messages    Posts on the current page
     * @param   KunenaPagination     $pagination  Pagination of the thread
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function topic(KunenaForumTopic $topic, KunenaForumCategory $category, array $messages, $pagination)
    {
        $first = KunenaForumMessageHelper::get((int) $topic->first_post_id);
        $url   = static::absolute($topic->getUrl($category, false));
        $page  = $pagination ? max(0, (int) $pagination->pagesCurrent - 1) : 0;
        $text  = $first->exists() && !$first->hold ? KunenaHtmlParser::plainText($first->message) : '';
        $image = static::messageImage($first);

        if (static::socialTagsEnabled()) {
            static::social(array(
                'type'        => 'article',
                'title'       => (string) $topic->subject,
                'description' => $text !== '' ? $text : (string) $topic->subject,
                'url'         => $page ? static::absolute($topic->getUrl($category, false, (string) $page)) : $url,
                'image'       => $image ?: static::defaultImage(),
                'article'     => array(
                    'published_time' => static::date($topic->first_post_time),
                    'modified_time'  => static::date($topic->last_post_time),
                    'section'        => (string) $category->name,
                ),
            ));
        }

        if (!static::structuredDataEnabled() || $topic->hold || !$first->exists() || $first->hold || !static::isPublic($category)) {
            return;
        }

        $data = array(
            '@type'         => 'DiscussionForumPosting',
            '@id'           => $url,
            // The first page, also on the later pages of the thread
            'url'           => $url,
            'headline'      => (string) $topic->subject,
            'datePublished' => static::date($first->time),
        );

        $data += static::author($first);

        if ($text !== '') {
            $data['text'] = $text;
        }

        if ($image) {
            $data['image'] = $image;
        }

        if ((int) $first->modified_time > (int) $first->time) {
            $data['dateModified'] = static::date($first->modified_time);
        }

        if (static::isAi($first)) {
            $data['digitalSourceType'] = static::AI_SOURCE;
        }

        $data['isPartOf'] = array(
            '@type' => 'WebPage',
            'name'  => (string) $category->name,
            'url'   => static::absolute($category->getUrl(null, false)),
        );

        $replies                      = max(0, (int) $topic->posts - 1);
        $data['commentCount']         = $replies;
        $data['interactionStatistic'] = array(
            static::counter('https://schema.org/CommentAction', $replies),
            static::counter('https://schema.org/ViewAction', (int) $topic->hits),
        );

        // The replies shown on this page
        $pageUrl  = $page ? static::absolute($topic->getUrl($category, false, (string) $page)) : $url;
        $comments = array();

        foreach ($messages as $message) {
            if (!$message instanceof KunenaForumMessage || $message->hold || (int) $message->id === (int) $first->id) {
                continue;
            }

            $comment = array(
                '@type'         => 'Comment',
                'url'           => $pageUrl . '#' . (int) $message->id,
                'datePublished' => static::date($message->time),
            );

            $comment += static::author($message);

            $commentText = KunenaHtmlParser::plainText($message->message);
            $commentImg  = static::messageImage($message);

            if ($commentText !== '') {
                $comment['text'] = $commentText;
            }

            if ($commentImg) {
                $comment['image'] = $commentImg;
            }

            if ($commentText === '' && !$commentImg) {
                continue;
            }

            if ((int) $message->modified_time > (int) $message->time) {
                $comment['dateModified'] = static::date($message->modified_time);
            }

            if (static::isAi($message)) {
                $comment['digitalSourceType'] = static::AI_SOURCE;
            }

            $comments[] = $comment;
        }

        if ($comments) {
            $data['comment'] = $comments;
        }

        static::jsonLd($data);
    }

    /**
     * Profile page: ProfilePage with the member as its main entity, and the sharing tags.
     *
     * @param   KunenaUser  $profile  Member
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function profile(KunenaUser $profile)
    {
        $url   = static::absolute((string) $profile->getURL(false));
        $name  = (string) $profile->getName('', false);
        $image = static::avatar($profile);

        if (static::socialTagsEnabled()) {
            static::social(array(
                'type'        => 'profile',
                'title'       => $name,
                'description' => (string) Factory::getDocument()->getDescription(),
                'url'         => $url ?: Uri::current(),
                'image'       => $image ?: static::defaultImage(),
            ));
        }

        // Profiles guests may not see are not described to search engines
        if (!static::structuredDataEnabled() || !$profile->exists() || !$url || !KunenaConfig::getInstance()->pubprofile) {
            return;
        }

        $person = array(
            '@type' => 'Person',
            'name'  => $name,
            'url'   => $url,
        );

        if ($image) {
            $person['image'] = $image;
        }

        $posts = (int) $profile->posts;

        if ($posts) {
            $person['agentInteractionStatistic'] = static::counter('https://schema.org/WriteAction', $posts);
        }

        $data = array(
            '@type'      => 'ProfilePage',
            'mainEntity' => $person,
        );

        if (!empty($profile->registerDate) && strpos((string) $profile->registerDate, '0000') !== 0) {
            $data['dateCreated'] = Factory::getDate($profile->registerDate)->toISO8601();
        }

        static::jsonLd($data);
    }

    /**
     * Other pages (forum index, category, lists): the sharing tags from the page's own title and description.
     *
     * @param   string  $title  Title (empty: the document's)
     * @param   string  $type   OpenGraph type
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function page($title = '', $type = 'website')
    {
        if (!static::socialTagsEnabled()) {
            return;
        }

        $doc = Factory::getDocument();

        static::social(array(
            'type'        => $type,
            'title'       => $title !== '' ? $title : static::pageTitle((string) $doc->getTitle()),
            'description' => (string) $doc->getDescription(),
            'url'         => static::canonical(),
            'image'       => static::defaultImage(),
        ));
    }

    /**
     * @param   string  $title  Document title
     *
     * @return  string  The title without the site's name (Global Configuration: "Site Name in Page Titles")
     *
     * @since 5.3.0
     */
    protected static function pageTitle($title)
    {
        $site = (string) Factory::getConfig()->get('sitename');

        if ($site !== '' && $title !== $site) {
            foreach (array($site . ' - ', ' - ' . $site) as $part) {
                if (strpos($title, $part) === 0) {
                    return substr($title, strlen($part));
                }

                if (substr($title, -strlen($part)) === $part) {
                    return substr($title, 0, -strlen($part));
                }
            }
        }

        return $title;
    }

    /**
     * Writes the OpenGraph and X (Twitter) tags.
     *
     * @param   array  $tags  type, title, description, url, image, article (published_time, modified_time, section)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function social(array $tags)
    {
        $config      = KunenaConfig::getInstance();
        $title       = static::clean(isset($tags['title']) ? $tags['title'] : '', 0);
        $description = static::clean(isset($tags['description']) ? $tags['description'] : '', static::DESCRIPTION_LENGTH);
        $image       = isset($tags['image']) ? (string) $tags['image'] : '';
        $site        = (string) Factory::getConfig()->get('sitename');

        $og = array(
            'og:type'        => isset($tags['type']) ? $tags['type'] : 'website',
            'og:site_name'   => $site,
            'og:title'       => $title,
            'og:description' => $description,
            'og:url'         => isset($tags['url']) ? (string) $tags['url'] : static::canonical(),
            'og:locale'      => str_replace('-', '_', Factory::getLanguage()->getTag()),
            'og:image'       => $image,
        );

        if (!empty($tags['article'])) {
            foreach ($tags['article'] as $name => $value) {
                $og['article:' . $name] = $value;
            }
        }

        $x = array(
            'twitter:card'        => $image ? 'summary_large_image' : 'summary',
            'twitter:title'       => $title,
            'twitter:description' => $description,
            'twitter:image'       => $image,
            'twitter:site'        => static::xHandle((string) $config->seo_x_site),
        );

        $og = array_filter(array_map('strval', $og), 'strlen');
        $x  = array_filter(array_map('strval', $x), 'strlen');

        if (static::viaPlatform()) {
            static::$pending['opengraph'] = $og;
            static::$pending['x']         = $x;

            return;
        }

        static::writeTags('opengraph', $og);
        static::writeTags('x', $x);
    }

    /**
     * @param   string  $family  opengraph or x
     * @param   array   $tags    name => content
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function writeTags($family, array $tags)
    {
        $doc = Factory::getDocument();

        foreach ($tags as $name => $value) {
            $doc->setMetaData($name, (string) $value, $family === 'opengraph' ? 'property' : 'name');
        }
    }

    /**
     * Adds a node of structured data: to Joomla 3.x UTD's graph, or as a JSON-LD block of its own.
     *
     * @param   array  $data  Node (without @context)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public static function jsonLd(array $data)
    {
        if (static::viaPlatform()) {
            static::$pending['graph'][] = $data;

            return;
        }

        static::writeJsonLd($data);
    }

    /**
     * Writes a JSON-LD block. A custom tag of its own: Joomla merges script declarations of the same type into one
     * element, which would join several JSON documents (also other extensions') into invalid JSON.
     *
     * @param   array  $data  Node (without @context)
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function writeJsonLd(array $data)
    {
        $json = json_encode(array('@context' => 'https://schema.org') + $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        if ($json !== false) {
            Factory::getDocument()->addCustomTag('<script type="application/ld+json">' . $json . '</script>');
        }
    }

    /**
     * @param   KunenaForumMessage  $message  Post
     *
     * @return  array  author (Person: name, url) of a post
     *
     * @since 5.3.0
     */
    protected static function author(KunenaForumMessage $message)
    {
        $person = array('@type' => 'Person', 'name' => '');

        if ((int) $message->userid) {
            $user           = KunenaUserHelper::get((int) $message->userid);
            $person['name'] = (string) $user->getName((string) $message->name, false);
            $profile        = KunenaConfig::getInstance()->pubprofile ? (string) $user->getURL(false) : '';

            if ($profile !== '') {
                $person['url'] = static::absolute($profile);
            }
        }

        if ($person['name'] === '') {
            $person['name'] = (string) $message->name !== '' ? (string) $message->name : 'Guest';
        }

        return array('author' => $person);
    }

    /**
     * @param   KunenaForumMessage  $message  Post
     *
     * @return  boolean  The post is Baldrick's (the AI first responder)
     *
     * @since 5.3.0
     */
    protected static function isAi(KunenaForumMessage $message)
    {
        if (!class_exists('KunenaBaldrick') || !(int) $message->userid) {
            return false;
        }

        $baldrick = KunenaBaldrick::getParams()->get('user_id', 0);

        return (int) $baldrick && (int) $baldrick === (int) $message->userid;
    }

    /**
     * @param   string   $type   schema.org action
     * @param   integer  $count  Count
     *
     * @return  array  InteractionCounter
     *
     * @since 5.3.0
     */
    protected static function counter($type, $count)
    {
        return array(
            '@type'                => 'InteractionCounter',
            'interactionType'      => $type,
            'userInteractionCount' => (int) $count,
        );
    }

    /**
     * @param   integer  $time  UNIX time
     *
     * @return  string  ISO 8601 date
     *
     * @since 5.3.0
     */
    protected static function date($time)
    {
        return Factory::getDate((int) $time)->toISO8601();
    }

    /**
     * The first image attached to a post, as an absolute URL.
     *
     * @param   KunenaForumMessage  $message  Post
     *
     * @return  string
     *
     * @since 5.3.0
     */
    protected static function messageImage(KunenaForumMessage $message)
    {
        if (!$message->exists() || $message->hold) {
            return '';
        }

        foreach (KunenaAttachmentHelper::getByMessage($message->id) as $attachment) {
            if ($attachment->isImage()) {
                return static::absolute((string) $attachment->getUrl(false, true, false));
            }
        }

        return '';
    }

    /**
     * @param   KunenaUser  $user  Member
     *
     * @return  string  Uploaded avatar as an absolute URL ('' for none)
     *
     * @since 5.3.0
     */
    protected static function avatar(KunenaUser $user)
    {
        if (!$user->avatar || strpos((string) $user->avatar, 'gallery/') === 0 || !is_file(JPATH_SITE . '/media/kunena/avatars/' . $user->avatar)) {
            return '';
        }

        return Uri::root() . 'media/kunena/avatars/' . $user->avatar;
    }

    /**
     * @return  string  The share image of the configuration, as an absolute URL ('' for none)
     *
     * @since 5.3.0
     */
    public static function defaultImage()
    {
        $image = trim((string) KunenaConfig::getInstance()->seo_share_image);

        if ($image === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        $image = ltrim(preg_replace('/#.*$/', '', $image), '/');

        return is_file(JPATH_SITE . '/' . $image) ? Uri::root() . $image : '';
    }

    /**
     * @param   string  $handle  X account (with or without @, or its URL)
     *
     * @return  string  "@name", or '' when invalid
     *
     * @since 5.3.0
     */
    public static function xHandle($handle)
    {
        $handle = preg_replace('#^https?://(www\.)?(twitter|x)\.com/#i', '', trim($handle));
        $handle = ltrim(trim($handle, '/'), '@');

        return preg_match('/^[A-Za-z0-9_]{1,15}$/', $handle) ? '@' . $handle : '';
    }

    /**
     * @return  string  The page's canonical URL (the canonical link if there is one), absolute
     *
     * @since 5.3.0
     */
    public static function canonical()
    {
        $doc = Factory::getDocument();

        if (isset($doc->_links) && is_array($doc->_links)) {
            foreach ($doc->_links as $href => $link) {
                if (is_array($link) && isset($link['relation']) && $link['relation'] === 'canonical') {
                    return static::absolute((string) $href);
                }
            }
        }

        return Uri::current();
    }

    /**
     * @param   string  $url  URL, relative to the site's host or absolute
     *
     * @return  string  Absolute URL
     *
     * @since 5.3.0
     */
    public static function absolute($url)
    {
        $url = html_entity_decode((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($url === '' || preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $host = Uri::getInstance(Uri::root())->toString(array('scheme', 'host', 'port'));

        if ($url[0] === '/') {
            return $host . $url;
        }

        return Uri::root() . $url;
    }

    /**
     * @param   string   $text    Text
     * @param   integer  $length  Maximum length (0: any)
     *
     * @return  string  One line of plain text
     *
     * @since 5.3.0
     */
    protected static function clean($text, $length)
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')));

        if ($length && \Joomla\String\StringHelper::strlen($text) > $length) {
            $text = rtrim(\Joomla\String\StringHelper::substr($text, 0, $length - 1)) . '…';
        }

        return $text;
    }
}

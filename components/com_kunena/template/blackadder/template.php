<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Blackadder: a modern, framework-free Kunena template.
 *
 * Plain CSS (Grid/Flex) scoped to the forum (.kb), no Bootstrap, inheriting the site template's fonts; a monochrome
 * scheme the site template can override with CSS variables (--kunena-*), dark mode through data-theme="dark" on any
 * ancestor; Bootstrap Icons (SVG sprite); vanilla JavaScript. Layouts not overridden here fall back to Crypsis.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaTemplateBlackadder extends KunenaTemplate
{
    /**
     * Parent templates (layouts not overridden here)
     *
     * @var array
     * @since 5.3.0
     */
    protected $default = array('crypsis');

    /**
     * @var array
     * @since 5.3.0
     */
    protected $pathTypes = array(
        'emoticons'     => 'media/kunena/emoticons',
        'ranks'         => 'media/kunena/ranks',
        'icons'         => 'media/kunena/icons',
        'categoryicons' => 'media/kunena/category_icons',
        'images'        => 'media/kunena/core/images',
        'js'            => 'media/kunena/core/js',
        'css'           => 'media/kunena/core/css',
    );

    /**
     * @var array
     * @since 5.3.0
     */
    protected $userClasses = array(
        'kwho-',
        'admin'     => 'kwho-admin',
        'globalmod' => 'kwho-globalmoderator',
        'moderator' => 'kwho-moderator',
        'user'      => 'kwho-user',
        'guest'     => 'kwho-guest',
        'banned'    => 'kwho-banned',
        'blocked'   => 'kwho-blocked',
    );

    /**
     * Kunena's icon names (KunenaIcons) => Bootstrap Icons
     *
     * @var array
     * @since 5.3.0
     */
    protected static $iconMap = array(
        'arrowdown'       => 'arrow-down',
        'arrowup'         => 'arrow-up',
        'arrowdownanchor' => 'arrow-down',
        'arrowupanchor'   => 'arrow-up',
        'chevronright'    => 'chevron-right',
        'members'         => 'people',
        'user'            => 'person',
        'lock'            => 'lock-fill',
        'star'            => 'star-fill',
        'shield'          => 'shield-check',
        'flag'            => 'flag',
        'poll'            => 'bar-chart',
        'poll_add'        => 'plus-circle',
        'poll_rem'        => 'dash-circle',
        'stats'           => 'graph-up',
        'search'          => 'search',
        'collapse'        => 'chevron-down',
        'clock'           => 'clock',
        'thumbsup'        => 'hand-thumbs-up',
        'cancel'          => 'x-lg',
        'ip'              => 'geo',
        'email'           => 'envelope',
        'bookmark'        => 'bookmark',
        'back'            => 'arrow-left',
        'save'            => 'check2',
        'edit'            => 'pencil',
        'pencil'          => 'pencil-square',
        'attach'          => 'paperclip',
        'plus'            => 'plus-lg',
        'rss'             => 'rss',
        'upload'          => 'upload',
        'picture'         => 'image',
        'file'            => 'file-earmark',
        'delete'          => 'trash',
        'undo'            => 'reply',
        'shuffle'         => 'shield-check',
        'home'            => 'house',
        'calendar'        => 'calendar',
        'hamburger'       => 'list',
        'info'            => 'info-circle',
        'online'          => 'circle-fill',
        'away'            => 'moon',
        'busy'            => 'dash-circle-fill',
        'invisible'       => 'eye-slash',
        'cog'             => 'gear',
        'drawer'          => 'inbox',
        'out'             => 'box-arrow-right',
        'grid'            => 'grid',
        'globe'           => 'globe',
        'location'        => 'geo-alt',
        'pm'              => 'chat-dots',
        'report'          => 'flag',
    );

    /**
     * Loads Crypsis's strings too (shared by the layouts Blackadder inherits).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function loadLanguage()
    {
        $lang = Factory::getLanguage();
        KunenaFactory::loadLanguage('com_kunena.templates', 'site');

        foreach (array('crypsis', 'blackadder') as $template) {
            $lang->load('kunena_tmpl_' . $template, JPATH_SITE)
            || $lang->load('kunena_tmpl_' . $template, KPATH_SITE)
            || $lang->load('kunena_tmpl_' . $template, KPATH_SITE . '/template/' . $template);
        }
    }

    /**
     * Template initialisation: the stylesheet and the script, nothing else (no Bootstrap, no icon fonts).
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function initialize()
    {
        $this->loadLanguage();

        // Kunena's own scripts (editor, uploads, mentions) still use jQuery
        HTMLHelper::_('jquery.framework');

        $doc = Factory::getDocument();

        $doc->addStyleSheet($this->assetUrl('assets/css/blackadder.css'));
        $doc->addScript($this->assetUrl('assets/js/blackadder.js'), array(), array('defer' => true));

        // Site-specific additions, kept across updates when the file is created by the site owner
        if (is_file(KPATH_SITE . '/template/blackadder/assets/css/custom.css')) {
            $doc->addStyleSheet($this->assetUrl('assets/css/custom.css'));
        }
    }

    /**
     * URL of one of the template's files with a timestamp of its last change (?t=YYYYMMDD_HHii, UTC), so browsers fetch
     * it again only when it changes.
     *
     * @param   string  $path  Path below the template's folder, e.g. "assets/css/blackadder.css"
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public function assetUrl($path)
    {
        $file = KPATH_SITE . '/template/blackadder/' . $path;
        $time = is_file($file) ? filemtime($file) : false;

        return Uri::root(true) . '/components/com_kunena/template/blackadder/' . $path . ($time ? '?t=' . gmdate('Ymd_Hi', $time) : '');
    }

    /**
     * An icon of the sprite (Bootstrap Icons).
     *
     * @param   string  $name   Bootstrap Icons name (e.g. "search")
     * @param   string  $label  Accessible label ('' for decorative icons)
     * @param   string  $class  Extra classes
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public function icon($name, $label = '', $class = '')
    {
        static $sprite = null;

        if ($sprite === null) {
            $sprite = $this->assetUrl('assets/images/icons.svg');
        }

        $name  = preg_replace('/[^a-z0-9-]/', '', (string) $name);
        $class = trim('kb-icon ' . preg_replace('/[^A-Za-z0-9 _-]/', '', (string) $class));
        $a11y  = $label !== ''
            ? 'role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
            : 'aria-hidden="true" focusable="false"';

        return '<svg class="' . $class . '" ' . $a11y . '><use href="' . $sprite . '#' . $name . '"></use></svg>';
    }

    /**
     * KunenaIcons calls this for every icon (see libraries/kunena/icons/icons.php).
     *
     * @param   string  $name  Kunena icon name
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public function svgIcon($name)
    {
        return $this->icon(isset(static::$iconMap[$name]) ? static::$iconMap[$name] : 'circle-fill');
    }

    /**
     * Blackadder is an HMVC template although it has no pages/ folder of its own (it uses Crypsis's pages): without this,
     * Kunena runs the forum's home page through its legacy controller, which calls itself until memory runs out.
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public function isHmvc()
    {
        return true;
    }

    /**
     * No Bootstrap tooltips: titles are native.
     *
     * @param   boolean  $class  Return the attribute (true) or the class (false)
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public function tooltips($class = false)
    {
        return '';
    }

    /**
     * Initials of a user, for avatars that are not images.
     *
     * @param   string  $name  Name
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function initials($name)
    {
        $parts    = preg_split('/[\s._-]+/u', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= \Joomla\String\StringHelper::strtoupper(\Joomla\String\StringHelper::substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }

    /**
     * A user's avatar: the image when there is one, otherwise initials.
     *
     * @param   KunenaUser  $user  User
     * @param   string      $size  sm, md, lg or xl
     * @param   string      $name  Name of a guest
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public function avatar($user, $size = 'md', $name = '')
    {
        $pixels = array('sm' => 48, 'md' => 64, 'lg' => 96, 'xl' => 192);
        $px     = isset($pixels[$size]) ? $pixels[$size] : 64;
        $label  = $user && $user->exists() ? $user->getName() : $name;
        $html   = '';

        // An uploaded avatar, or one from an integration (Gravatar, Community Builder...); Kunena's own "no photo"
        // image becomes initials
        if ($user && $user->exists() && ($user->avatar || !(KunenaFactory::getAvatarIntegration() instanceof KunenaAvatarKunena))) {
            $url = $user->getAvatarURL($px, $px);

            if ($url) {
                $html = '<img src="' . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" loading="lazy" width="' . $px . '" height="' . $px . '">';
            }
        }

        if ($html === '') {
            $html = htmlspecialchars(static::initials($label), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $class = 'kb-avatar kb-avatar--' . preg_replace('/[^a-z]/', '', $size);

        if ($user && $user->exists() && $user->userid == static::baldrickId()) {
            $class .= ' kb-avatar--ai';
        }

        return '<span class="' . $class . '" title="' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $html . '</span>';
    }

    /**
     * Posts awaiting approval in the categories the current user may approve (0 for everyone else).
     *
     * @return  integer
     *
     * @since 5.3.0
     */
    public static function pendingApproval()
    {
        static $count = null;

        if ($count !== null) {
            return $count;
        }

        $count = 0;
        $me    = KunenaUserHelper::getMyself();

        if (!$me->exists() || !$me->isModerator()) {
            return $count;
        }

        $categories = KunenaForumCategoryHelper::getCategories(false, false, 'topic.post.approve');

        if ($categories) {
            $db    = Factory::getDbo();
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__kunena_messages'))
                ->where($db->quoteName('hold') . ' = 1')
                ->where($db->quoteName('catid') . ' IN (' . implode(',', array_map('intval', array_keys($categories))) . ')');

            try {
                $count = (int) $db->setQuery($query)->loadResult();
            } catch (Exception $e) {
                $count = 0;
            }
        }

        return $count;
    }

    /**
     * @return  integer  Baldrick's user ID when the AI assistant is enabled (its posts get their own avatar style)
     *
     * @since 5.3.0
     */
    public static function baldrickId()
    {
        static $id = null;

        if ($id === null) {
            $id = class_exists('KunenaBaldrick') && KunenaBaldrick::isEnabled() ? (int) KunenaBaldrick::getUserId() : 0;
        }

        return $id;
    }
}

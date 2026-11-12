<?php

/**
 * Kunena Plugin
 *
 * @package         Kunena.Plugins
 * @subpackage      Kunena
 *
 * @copyright       Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license         https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link            https://www.kunena.org
 **/
defined('_JEXEC') or die();

/**
 * Class KunenaAvatarKunena
 * @since Kunena
 */
#[\AllowDynamicProperties]
class KunenaAvatarKunena extends KunenaAvatar
{
    /**
     * A default avatar chosen with the Media Manager: a path from the site's root (e.g. images/avatar.png) to an existing
     * image. '' for a plain file name (searched in the template, as before) or anything else.
     *
     * @param   string  $value  Configuration value
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function defaultImagePath($value)
    {
        $value = ltrim(preg_replace('/#.*$/', '', (string) $value), '/');

        if (strpos($value, '/') === false || strpos($value, '..') !== false || strpbrk($value, "\\\0:") !== false
            || !preg_match('/\.(gif|jpe?g|png|webp|avif)$/i', $value) || !is_file(JPATH_SITE . '/' . $value)) {
            return '';
        }

        return $value;
    }

    /**
     * @var null
     * @since Kunena
     */
    protected $params = null;

    /**
     * @var null
     * @since Kunena
     */
    public $css = true;

    /**
     * @param $params
     *
     * @since Kunena
     */
    public function __construct($params)
    {
        $this->params = $params;
        $this->resize = true;
    }

    /**
     * @return boolean
     * @throws Exception
     * @since Kunena
     * @throws null
     */
    public function getEditURL()
    {
        return KunenaRoute::_('index.php?option=com_kunena&view=user&layout=edit');
    }

    /**
     * @param $user
     * @param $sizex
     * @param $sizey
     *
     * @return string
     * @throws Exception
     * @since Kunena
     */
    protected function _getURL($user, $sizex, $sizey)
    {
        $user   = KunenaFactory::getUser($user);
        $avatar = $user->avatar;
        $config = KunenaFactory::getConfig();

        $path     = KPATH_MEDIA . "/avatars";

        // Security: the avatar must be a plain relative path inside the avatars folder (no traversal).
        if (!is_string($avatar) || $avatar === '' || strpos($avatar, '..') !== false || strpbrk($avatar, "\\\0:") !== false
            || $avatar[0] === '/') {
            $avatar = '';
        }

        $origPath = "{$path}/{$avatar}";

        if ($avatar === '' || !is_file($origPath)) {
            // If avatar does not exist use default image.
            if ($sizex <= 90) {
                $avatar = (string) KunenaConfig::getInstance()->defaultavatarsmall ?: 's_nophoto.png';
            } else {
                $avatar = (string) KunenaConfig::getInstance()->defaultavatar ?: 'nophoto.png';
            }

            $custom = KunenaAvatarKunena::defaultImagePath($avatar);

            if ($custom !== '') {
                // An image chosen with the Media Manager (a path from the site's root)
                $origPath = JPATH_SITE . '/' . $custom;
                $avatar   = 'default/' . md5($custom) . '/' . basename($custom);
            } else {
                // A file name: searched in the template (and Kunena's avatars folder)
                $template = KunenaFactory::getTemplate();
                $origPath = JPATH_SITE . '/' . $template->getAvatarPath($avatar);
                $avatar   = $template->name . '/' . $avatar;
            }
        }

        $dir  = dirname($avatar);
        $file = basename($avatar);

        if ($sizex == $sizey) {
            $resized = "resized/size{$sizex}/{$dir}";
        } else {
            $resized = "resized/size{$sizex}x{$sizey}/{$dir}";
        }

        if ($user->timestamp) {
            $timestamp = '?' . $user->timestamp;
        } else {
            $timestamp = '';
        }

        if (!is_file("{$path}/{$resized}/{$file}")) {
            KunenaImageHelper::version(
                $origPath,
                "{$path}/{$resized}",
                $file,
                $sizex,
                $sizey,
                intval($config->avatarquality),
                KunenaImage::SCALE_INSIDE,
                intval($config->avatarcrop)
            );

            if ($user->timestamp) {
                $timestamp = '?' . $user->timestamp;
            } else {
                $timestamp = '?' . round(microtime(true));
            }
        }

        return KURL_MEDIA . "avatars/{$resized}/{$file}{$timestamp}";
    }
}

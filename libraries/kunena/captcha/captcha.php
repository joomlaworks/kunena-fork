<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Captcha
 *
 * @copyright     Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;

/**
 * CAPTCHA integration.
 *
 * Kunena no longer talks to a specific CAPTCHA service (it used to be hard-wired to Google reCAPTCHA's
 * plugin parameters). The CAPTCHA plugin selected in Joomla's Global Configuration ("Default Captcha")
 * is used through Joomla's own Captcha API, so every CAPTCHA plugin works: reCAPTCHA (checkbox,
 * invisible, v3), hCaptcha, Cloudflare Turnstile, IconCaptcha, Akismet or any third-party plugin.
 *
 * Validation fails closed: when a user must solve a CAPTCHA (see KunenaUser::canDoCaptcha()) but no
 * usable CAPTCHA plugin is configured, the post is refused instead of being silently accepted.
 *
 * @since 5.3.0
 */
abstract class KunenaCaptcha
{
    /**
     * Name of the request variable used for the CAPTCHA control. Plugins which post their own fields
     * (e.g. g-recaptcha-response, h-captcha-response, cf-turnstile-response) read those themselves.
     *
     * @since 5.3.0
     */
    public const FIELD_NAME = 'kunena_captcha';

    /**
     * Namespace used for the Captcha instances.
     *
     * @since 5.3.0
     */
    public const NAMESPACE_NAME = 'com_kunena';

    /**
     * Returns the CAPTCHA plugin selected in Joomla's Global Configuration, if it is enabled.
     *
     * @return  string  Plugin element or an empty string when no usable CAPTCHA is configured.
     *
     * @since 5.3.0
     */
    public static function getPlugin()
    {
        try {
            $plugin = (string) Factory::getApplication()->get('captcha', '');
        } catch (Exception $e) {
            $plugin = (string) Factory::getConfig()->get('captcha', '');
        }

        $plugin = trim($plugin);

        if ($plugin === '' || $plugin === '0' || !PluginHelper::isEnabled('captcha', $plugin)) {
            return '';
        }

        return $plugin;
    }

    /**
     * Is a CAPTCHA plugin configured and enabled?
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isAvailable()
    {
        return self::getPlugin() !== '';
    }

    /**
     * Does the user need to solve a CAPTCHA before posting?
     *
     * @param   KunenaUser|null  $user  User, defaults to the current user.
     *
     * @return  boolean
     *
     * @since 5.3.0
     */
    public static function isRequired(?KunenaUser $user = null)
    {
        if ($user === null) {
            $user = KunenaUserHelper::getMyself();
        }

        return (bool) $user->canDoCaptcha();
    }

    /**
     * Returns the HTML of the CAPTCHA control.
     *
     * @param   string  $id     Unique id of the control in the page.
     * @param   string  $class  CSS class for the control.
     *
     * @return  string  HTML, or a warning when CAPTCHA is required but not configured.
     *
     * @since 5.3.0
     */
    public static function display($id = 'kunena_captcha', $class = 'kunena-captcha')
    {
        $plugin = self::getPlugin();

        if ($plugin === '') {
            return '<div class="alert alert-warning">'
                . htmlspecialchars(Text::sprintf('COM_KUNENA_RECAPTCHA_ERROR_INVALID_CONFIGURATION', Text::_('COM_KUNENA_CAPTCHA_NOT_SELECTED')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</div>';
        }

        // The id is used as an HTML id and in JavaScript.
        $id = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $id);

        try {
            return (string) Captcha::getInstance($plugin, array('namespace' => self::NAMESPACE_NAME))->display(self::FIELD_NAME, $id, $class);
        } catch (Exception $e) {
            self::log($e->getMessage());

            return '<div class="alert alert-warning">'
                . htmlspecialchars(Text::sprintf('COM_KUNENA_RECAPTCHA_ERROR_INVALID_CONFIGURATION', $plugin), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</div>';
        }
    }

    /**
     * Validates the CAPTCHA answer of the current request, when the user is required to solve one.
     *
     * @param   KunenaUser|null  $user   User, defaults to the current user.
     * @param   string           $error  Receives the error message on failure.
     *
     * @return  boolean  True when the user may continue.
     *
     * @since 5.3.0
     */
    public static function verify(?KunenaUser $user = null, &$error = null)
    {
        $error = null;

        if (!self::isRequired($user)) {
            return true;
        }

        $plugin = self::getPlugin();

        // Fail closed: never accept a post which should have been protected by a CAPTCHA.
        if ($plugin === '') {
            self::log('A CAPTCHA is required by Kunena\'s configuration, but no CAPTCHA plugin is selected or enabled in Global Configuration.');
            $error = Text::sprintf('COM_KUNENA_RECAPTCHA_ERROR_INVALID_CONFIGURATION', Text::_('COM_KUNENA_CAPTCHA_NOT_SELECTED'));

            return false;
        }

        try {
            $code   = Factory::getApplication()->input->post->get(self::FIELD_NAME, '', 'string');
            $result = Captcha::getInstance($plugin, array('namespace' => self::NAMESPACE_NAME))->checkAnswer($code);
        } catch (Exception $e) {
            // Plugins report a missing or wrong answer by throwing an exception.
            $error = $e->getMessage() ?: Text::_('COM_KUNENA_RECAPTCHA_ERROR_INCORRECT_CAPTCHA_SOL');

            return false;
        }

        if (!$result) {
            $error = Text::_('COM_KUNENA_RECAPTCHA_ERROR_INCORRECT_CAPTCHA_SOL');

            return false;
        }

        return true;
    }

    /**
     * Log a CAPTCHA configuration problem for the site administrator.
     *
     * @param   string  $message  Message.
     *
     * @return  void
     *
     * @since 5.3.0
     */
    protected static function log($message)
    {
        try {
            Log::add('Kunena CAPTCHA: ' . $message, Log::WARNING, 'kunena');
        } catch (Exception $e) {
            // Logging must never break posting.
        }
    }
}

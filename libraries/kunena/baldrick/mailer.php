<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Framework
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Plain-text notifications about Baldrick's replies and spam actions, sent through Joomla's mail settings to the chosen
 * administrators/moderators and addresses. They hold the first post of the thread and its last five posts, newest first.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
abstract class KunenaBaldrickMailer
{
    /**
     * Errors of the last send(), one per recipient that failed
     *
     * @var    string[]
     * @since  5.3.0
     */
    public static $errors = array();

    /**
     * Latest posts included in a notification (plus the first post)
     *
     * @since 5.3.0
     */
    public const POSTS = 5;

    /**
     * @return  string[]  Recipients' addresses
     *
     * @since 5.3.0
     */
    public static function getRecipients()
    {
        $params = KunenaBaldrick::getParams();
        $emails = array();

        foreach ((array) $params->get('notify_users', array()) as $id) {
            if (!KunenaBaldrick::userExists($id)) {
                continue;
            }

            $user = Factory::getUser((int) $id);

            if ($user->id && !$user->block && MailHelper::isEmailAddress($user->email)) {
                $emails[strtolower($user->email)] = $user->email;
            }
        }

        foreach (preg_split('/[\s,;]+/', (string) $params->get('notify_emails', ''), -1, PREG_SPLIT_NO_EMPTY) as $email) {
            if (MailHelper::isEmailAddress($email)) {
                $emails[strtolower($email)] = $email;
            }
        }

        return array_values($emails);
    }

    /**
     * Sends the notification.
     *
     * @param   KunenaForumTopic  $topic   Thread
     * @param   array             $posts   Posts, oldest first (Baldrick's reply included)
     * @param   string            $action  replied, canned, info, spam or spam_unpublished
     * @param   string            $note    Details
     * @param   object            $run     Run (tokens, cost, models, job)
     *
     * @return  integer|null  E-mails sent, null when there are no recipients
     *
     * @since 5.3.0
     */
    public static function send(KunenaForumTopic $topic, array $posts, $action, $note, $run)
    {
        $recipients     = static::getRecipients();
        static::$errors = array();

        if (!$recipients) {
            return null;
        }

        KunenaBaldrickEngine::loadLanguage();

        $config  = Factory::getConfig();
        $subject = Text::sprintf('COM_KUNENA_BALDRICK_MAIL_SUBJECT_' . strtoupper($action), $config->get('sitename'), $topic->subject);

        return static::deliver($recipients, $subject, static::body($topic, $posts, $action, $note, $run));
    }

    /**
     * Tells the recipients that Baldrick paused itself after an error of the account or the configuration.
     *
     * @param   string  $reason  Error
     *
     * @return  integer  E-mails sent
     *
     * @since 5.3.0
     */
    public static function sendPaused($reason)
    {
        $recipients = static::getRecipients();

        if (!$recipients) {
            return 0;
        }

        KunenaBaldrickEngine::loadLanguage();

        $config  = Factory::getConfig();
        $minutes = max(1, (int) KunenaBaldrick::getParams()->get('pause_minutes', 15));
        $body    = Text::sprintf('COM_KUNENA_BALDRICK_MAIL_PAUSED_BODY', $reason, $minutes, rtrim(Uri::root(), '/') . '/administrator/index.php?option=com_kunena&view=baldrick');
        $body    = str_replace('\\n', "\n", $body);

        return static::deliver($recipients, Text::sprintf('COM_KUNENA_BALDRICK_MAIL_SUBJECT_PAUSED', $config->get('sitename')), $body);
    }

    /**
     * @param   string[]  $recipients  Addresses
     * @param   string    $subject     Subject
     * @param   string    $body        Plain text
     *
     * @return  integer  E-mails sent
     *
     * @since 5.3.0
     */
    protected static function deliver(array $recipients, $subject, $body)
    {
        $config = Factory::getConfig();
        $sent   = 0;

        foreach ($recipients as $recipient) {
            $mailer = Factory::getMailer();
            $mailer->setSender(array($config->get('mailfrom'), $config->get('fromname')));
            $mailer->addRecipient($recipient);
            $mailer->setSubject($subject);
            $mailer->isHtml(false);
            $mailer->Encoding = 'base64';
            $mailer->setBody($body);

            try {
                $result = $mailer->Send();

                if ($result === true) {
                    $sent++;
                } else {
                    // Joomla returns false (mail disabled in the Global Configuration) or an error object
                    static::$errors[] = is_object($result) && method_exists($result, 'getMessage') ? $result->getMessage()
                        : ($config->get('mailonline', 1) ? ($mailer->ErrorInfo ?: 'the mailer refused it') : 'sending e-mails is disabled in the Global Configuration');
                }
            } catch (Exception $e) {
                static::$errors[] = $e->getMessage();
            }
        }

        return $sent;
    }

    /**
     * @param   KunenaForumTopic  $topic   Thread
     * @param   array             $posts   Posts, oldest first
     * @param   string            $action  Action
     * @param   string            $note    Details
     * @param   object            $run     Run
     *
     * @return  string
     *
     * @since 5.3.0
     */
    public static function body(KunenaForumTopic $topic, array $posts, $action, $note, $run)
    {
        $root  = !empty($run->job->site_url) ? $run->job->site_url : Uri::root();
        $root  = rtrim($root, '/') . '/';
        $lines = array();

        $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_INTRO_' . strtoupper($action));
        $lines[] = '';
        $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_THREAD') . ': ' . $topic->subject;
        $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_CATEGORY') . ': ' . $topic->getCategory()->name;
        $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_LINK') . ': ' . $root . KunenaBaldrick::topicLink($topic->category_id, $topic->id);
        $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_DETAILS') . ': ' . $note;

        if ($run->models) {
            $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_MODELS') . ': ' . implode(' > ', array_unique(array_values($run->models)));
            $lines[] = Text::sprintf('COM_KUNENA_BALDRICK_MAIL_USAGE', number_format($run->input), number_format($run->output), number_format($run->cache), KunenaBaldrickUsage::formatCost($run->cost));
        }

        if ($run->dryRun) {
            $lines[] = Text::_('COM_KUNENA_BALDRICK_MAIL_DRY_RUN');
        }

        $lines[] = '';
        $lines[] = Text::sprintf('COM_KUNENA_BALDRICK_MAIL_POSTS', static::POSTS);
        $lines[] = '';

        foreach (static::selectPosts($posts) as $post) {
            $role = $post['role'] === 'assistant' ? ' (Baldrick)' : ($post['role'] === 'staff' ? ' (' . Text::_('COM_KUNENA_BALDRICK_MAIL_STAFF') . ')' : '');

            $lines[] = str_repeat('=', 72);
            $lines[] = '#' . $post['number'] . ' ' . $post['author'] . $role . ' - ' . gmdate('Y-m-d H:i', $post['time']) . ' UTC'
                . ($post['hold'] ? ' [' . Text::_('COM_KUNENA_BALDRICK_MAIL_UNPUBLISHED') . ']' : '');
            $lines[] = str_repeat('=', 72);
            $lines[] = $post['text'];
            $lines[] = '';

            if (!empty($post['gap'])) {
                $lines[] = '[...]';
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The last posts, newest first, then the first post (once).
     *
     * @param   array  $posts  Posts, oldest first
     *
     * @return  array
     *
     * @since 5.3.0
     */
    public static function selectPosts(array $posts)
    {
        foreach ($posts as $i => $post) {
            $posts[$i]['number'] = $i + 1;
        }

        $last     = array_slice($posts, -static::POSTS);
        $selected = array_reverse($last);

        if ($posts && $last[0]['number'] !== 1) {
            if ($last[0]['number'] > 2) {
                $selected[count($selected) - 1]['gap'] = true;
            }

            $selected[] = $posts[0];
        }

        return $selected;
    }
}

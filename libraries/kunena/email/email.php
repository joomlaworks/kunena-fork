<?php

/**
 * Kunena Component
 * @package       Kunena.Framework
 * @subpackage    Email
 *
 * @copyright     Copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die();

use Joomla\CMS\Log\Log;

/**
 * Class KunenaEmail
 * @since Kunena
 */
#[\AllowDynamicProperties]
abstract class KunenaEmail
{
    /**
     * @param   \Joomla\CMS\Mail\Mail $mail      mail
     * @param   array                 $receivers receivers
     *
     * @return boolean
     * @throws Exception
     * @since Kunena
     */
    public static function send($mail, array $receivers)
    {
        $config = KunenaFactory::getConfig();

        // Development: no e-mails, a line in Kunena's log instead
        if (static::isDisabled()) {
            Log::add(sprintf('E-mail not sent (Kunena > Configuration > Development): "%s" to %d recipient(s)', (string) $mail->Subject, count($receivers)), Log::INFO, 'kunena');

            return true;
        }

        if (!empty($config->email_recipient_count)) {
            $email_recipient_count = $config->email_recipient_count;
        } else {
            $email_recipient_count = 1;
        }

        $email_recipient_privacy = $config->get('email_recipient_privacy', 'bcc');

        // If we hide email addresses from other users, we need to add TO address to prevent email from becoming spam.
        if ($email_recipient_count > 1
            && $email_recipient_privacy == 'bcc'
            && \Joomla\CMS\Mail\MailHelper::isEmailAddress($config->get('email_visible_address'))
        ) {
            $mail->AddAddress($config->email_visible_address, \Joomla\CMS\Mail\MailHelper::cleanAddress($config->board_title));

            // Also make sure that email receiver limits are not violated (TO + CC + BCC = limit).
            if ($email_recipient_count > 9) {
                $email_recipient_count--;
            }
        }

        $chunks = array_chunk($receivers, $email_recipient_count);

        $success = true;

        foreach ($chunks as $emails) {
            if ($email_recipient_count == 1 || $email_recipient_privacy == 'to') {
                $mail->ClearAddresses();
                $mail->addRecipient($emails);
            } elseif ($email_recipient_privacy == 'cc') {
                $mail->ClearCCs();
                $mail->addCC($emails);
            } else {
                $mail->ClearBCCs();
                $mail->addBCC($emails);
            }

            try {
                $mail->Send();
            } catch (Exception $e) {
                $success = false;
                Log::add($e->getMessage(), Log::ERROR, 'kunena');
            }
        }

        return $success;
    }

    /**
     * @return  boolean  Kunena sends no e-mails (Kunena > Configuration > Development)
     *
     * @since 5.3.0
     */
    public static function isDisabled()
    {
        return (bool) KunenaFactory::getConfig()->get('dev_disable_email', 0);
    }
}

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

/**
 * Kunena activity integration of Baldrick: Kunena calls onAfterPost()/onAfterReply() whenever a post becomes visible
 * (posted in an unmoderated category, or approved).
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickActivity extends KunenaActivity
{
    /**
     * @param   KunenaForumMessage  $message  New thread
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function onAfterPost($message)
    {
        if ($message instanceof KunenaForumMessage) {
            KunenaBaldrick::onPost($message);
        }
    }

    /**
     * @param   KunenaForumMessage  $message  Reply
     *
     * @return  void
     *
     * @since 5.3.0
     */
    public function onAfterReply($message)
    {
        if ($message instanceof KunenaForumMessage) {
            KunenaBaldrick::onPost($message);
        }
    }
}

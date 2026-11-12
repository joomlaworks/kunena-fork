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
 * A post written by Baldrick.
 *
 * Kunena's flood protection and its "identical message within 5 minutes" check compare the author's IP address: they
 * protect forms from people, and would refuse Baldrick's canned replies (identical by design) in busy moments. They are
 * skipped by checking the post with an address no other post has; the stored address stays empty.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class KunenaBaldrickMessage extends KunenaForumMessage
{
    /**
     * @return  boolean
     *
     * @since 5.3.0
     */
    public function check()
    {
        $ip       = $this->ip;
        $this->ip = 'baldrick-' . uniqid('', true);

        try {
            return parent::check();
        } finally {
            $this->ip = $ip;
        }
    }
}

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.User
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// Social profiles as links (or plain text when the network has no profile address)
$links = array();

foreach ((array) $this->socials as $key => $social) {
    $social = (array) $social;

    if (empty($this->profile->$key) || empty($social['title'])) {
        continue;
    }

    $value = (string) $this->profile->$key;
    $title = $social['title'];

    if (empty($social['nourl'])) {
        $url     = strtr($social['url'], array('##VALUE##' => $value));
        $links[] = '<a class="kb-chip" href="' . $this->escape($url) . '" target="_blank" rel="nofollow noopener noreferrer me">' . $title . '</a>';
    } else {
        $links[] = '<span class="kb-chip">' . $title . ': ' . $this->escape($value) . '</span>';
    }
}

if (!$links) {
    return;
}
?>
<div class="kb-profile-social"><?php echo implode('', $links); ?></div>

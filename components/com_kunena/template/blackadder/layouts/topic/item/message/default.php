<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Topic
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


// One post: the post itself (Message/Item) with its actions inside its footer. Kunena's actions controller echoes a
// "Please log in to join the conversation" line for guests under every post: the reply box at the bottom says it once.
ob_start();
$actions = (string) $this->subRequest('Message/Item/Actions')->set('mesid', $this->message->id);
ob_end_clean();

echo $this->subLayout('Message/Item')
    ->setProperties($this->getProperties())
    ->set('actionsHtml', $actions);

echo $this->subLayout('Widget/Module')->set('position', 'kunena_msg_' . $this->message->replynum);

<?php

/**
 * Kunena Component
 *
 * @package       Kunena.Template.Blackadder
 * @subpackage    Layout.Widget
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die;


use Joomla\CMS\Language\Text;

$item = $this->item;

if ($item->base !== null) {
    echo '<li><a href="' . $item->link . '" title="' . Text::_('COM_KUNENA_PAGE') . ' ' . $item->text . '">' . $item->text . '</a></li>';
} elseif (!empty($item->active)) {
    echo '<li><span aria-current="page">' . $item->text . '</span></li>';
}

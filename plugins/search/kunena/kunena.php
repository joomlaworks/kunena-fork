<?php

/**
 * Kunena Search Plugin
 *
 * @package       Kunena.plg_search_kunena
 *
 * @copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die();

// Kunena detection and version check
$minKunenaVersion = '5.0';

if (!class_exists('KunenaForum') || !KunenaForum::isCompatible($minKunenaVersion)) {
    return;
}

// Kunena online check
if (!KunenaForum::enabled()) {
    return;
}

// Setup Kunena framework
KunenaForum::setup();
KunenaFactory::loadLanguage('com_kunena.sys', 'admin');

// Initialize plugin
$app = JFactory::getApplication();
$app->registerEvent('onContentSearch', 'plgSearchKunena');
$app->registerEvent('onContentSearchAreas', 'plgSearchKunenaAreas');

/**
 * Function to return an array of search areas.
 *
 * @return mixed
 */
function &plgSearchKunenaAreas()
{
    static $areas = array();

    if (empty($areas)) {
        $areas['kunena'] = JText::_('COM_KUNENA');
    }

    return $areas;
}

/**
 * @param        $text
 * @param   string $phrase
 * @param   string $ordering
 * @param   null   $areas
 *
 * @return array
 */
function plgSearchKunena($text, $phrase = '', $ordering = '', $areas = null)
{
    // If the array is not correct, return it:
    if (is_array($areas) && !array_intersect($areas, array_keys(plgSearchKunenaAreas()))) {
        return array();
    }

    $plugin       = JPluginHelper::getPlugin('search', 'kunena');
    $pluginParams = new JRegistry();
    $pluginParams->loadString($plugin->params);

    // And define the parameters. For example like this..
    $limit         = max(1, min(500, (int) $pluginParams->get('search_limit', 50)));
    $contentLimit  = max(1, (int) $pluginParams->get('content_limit', 40));
    $bbcode        = $pluginParams->get('show_bbcode', 1);
    $openInNewPage = $pluginParams->get('open_new_page', 1);

    // Use the function trim to delete spaces in front of or at the back of the searching terms
    $text = trim((string) $text);

    // Return Array when nothing was filled in
    if ($text == '') {
        return array();
    }

    $db = JFactory::getDbo();

    // Case-insensitive on every database (PostgreSQL's LIKE is case-sensitive). Security: the LIKE wildcards % and _
    // of the search words are escaped too ($db->escape($value, true)).
    $like = KunenaDatabaseHelper::like($db);

    switch ($phrase) {
        // Search exact
        case 'exact':
            $text  = $db->quote('%' . $db->escape($text, true) . '%', false);
            $where = "(m.subject {$like} {$text} OR t.message {$like} {$text})";
            break;

            // Search all or any
        case 'all':
        case 'any':
        default:
            $where = array();
            $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

            // At most 20 words: each one is two LIKE conditions on the whole message table
            foreach (array_slice(array_unique((array) $words), 0, 20) as $word) {
                $word     = $db->quote('%' . $db->escape($word, true) . '%', false);
                $where [] = "m.subject {$like} {$word} OR t.message {$like} {$word}";
            }

            if (!$where) {
                return array();
            }

            $where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $where) . ')';
            break;
    }

    // Ordering of the results
    switch ($ordering) {
        // Oldest first
        case 'oldest':
            $orderby = 'm.time ASC';
            break;

            // Popular first
        case 'popular':
            $orderby = 'm.hits DESC, m.time DESC';
            break;

            // Newest first
        case 'newest':
            $orderby = 'm.time DESC';
            break;

            // Alphabetic, ascending
        case 'alpha':
            // Default setting: alphabetic, ascending
        default:
            $orderby = 'm.subject ASC, m.time DESC';
    }

    $params = array('orderby' => $orderby, 'where' => $where, 'starttime' => -1);
    list($total, $messages) = KunenaForumMessageHelper::getLatestMessages(false, 0, $limit, $params);
    $rows = array();

    foreach ($messages as $message) {
        /** @var KunenaForumMessage $message */
        // Function must return: href, title, section, created, text, browsernav
        $row          = new StdClass();
        $row->id      = $message->id;
        $row->href    = $message->getUrl();

        // Plain text, HTML-escaped (com_search decodes entities and strips tags, other consumers print it as it is)
        $row->title   = htmlspecialchars(Joomla\String\StringHelper::substr((string) $message->subject, 0, $contentLimit), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // com_search escapes the section itself
        $row->section = $message->getCategory()->name;
        $row->created = $message->time;

        if ($bbcode) {
            // The message is the parser's parent (confidential text: author and moderators only); the length limit
            // used to be passed as the parent.
            $row->text = KunenaHtmlParser::parseBBCode($message->message, $message, $contentLimit);
        } else {
            $row->text = KunenaHtmlParser::stripBBCode($message->message, $contentLimit);
        }

        $row->browsernav = $openInNewPage ? 1 : 0;
        $rows[]          = $row;
    }

    // Return the search results in an array
    return $rows;
}

<?php

/**
 * Kunena - Baldrick AI
 *
 * @package       Kunena.Plugins
 * @subpackage    Baldrick
 *
 * @copyright     Copyright (C) 2026 JoomlaWorks Ltd. All rights reserved.
 * @license       https://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.joomlaworks.net
 **/

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Model name with "Fetch models" (lists the provider's models from the values in the form, saved or not) and "Test"
 * buttons. The name can always be typed: not every compatible endpoint lists its models.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class JFormFieldBaldrickModel extends \Joomla\CMS\Form\FormField
{
    /**
     * @var string
     * @since 5.3.0
     */
    protected $type = 'BaldrickModel';

    /**
     * @return  string
     *
     * @since 5.3.0
     */
    protected function getInput()
    {
        static $loaded = false;

        if (!$loaded) {
            $loaded = true;

            HTMLHelper::_('jquery.framework');

            // Joomla's searchable lists (Chosen; Joomla 3.x UTD: its vanilla JS version): loads the scripts and styles. The
            // model lists are made searchable by baldrick.js once they have options.
            HTMLHelper::_('formbehavior.chosen', 'select.baldrick-model-list-chosen');

            Factory::getDocument()->addScript(Uri::root(true) . '/plugins/kunena/baldrick/assets/baldrick.js', array('version' => 'auto'));
            Factory::getDocument()->addScriptOptions('plg_kunena_baldrick', array(
                'url'     => Uri::base(true) . '/index.php?option=com_kunena&view=baldrick&format=json',
                'token'   => Session::getFormToken(),
                'working' => Text::_('PLG_KUNENA_BALDRICK_WORKING'),
                'choose'  => Text::_('PLG_KUNENA_BALDRICK_CHOOSE_MODEL'),
                'failed'  => Text::_('PLG_KUNENA_BALDRICK_REQUEST_FAILED'),
                'search'  => Text::_('PLG_KUNENA_BALDRICK_SEARCH_MODELS'),
                'nomatch' => Text::_('PLG_KUNENA_BALDRICK_NO_MODEL_MATCH'),
                'prices'  => Text::_('PLG_KUNENA_BALDRICK_PRICES_FILLED'),
            ));
        }

        $stage = (string) $this->element['stage'] === 'answer' ? 'answer' : 'triage';
        $id    = htmlspecialchars($this->id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $e = function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        return '<div class="baldrick-model" data-stage="' . $stage . '">'
            . '<input type="text" name="' . $e($this->name) . '" id="' . $id . '" value="' . $e($this->value) . '" class="input-xlarge baldrick-model-input" autocomplete="off"/> '
            . '<button type="button" class="btn baldrick-fetch"><span class="icon-list"></span> ' . Text::_('PLG_KUNENA_BALDRICK_FETCH_MODELS') . '</button> '
            . '<button type="button" class="btn baldrick-test"><span class="icon-flash"></span> ' . Text::_('PLG_KUNENA_BALDRICK_TEST') . '</button>'
            . '<div class="baldrick-model-picker" style="display:none; margin-top:8px; max-width:40em;">'
            . '<select class="baldrick-model-list" data-searchable="false" aria-label="' . $e(Text::_('PLG_KUNENA_BALDRICK_SEARCH_MODELS')) . '"></select>'
            . '<div class="small" style="margin-top:4px;">'
            . '<label class="checkbox inline" title="' . $e(Text::_('PLG_KUNENA_BALDRICK_SHOW_ALL_MODELS_DESC')) . '"><input type="checkbox" class="baldrick-show-all"/> ' . Text::_('PLG_KUNENA_BALDRICK_SHOW_ALL_MODELS') . '</label>'
            . ' &nbsp; <a href="#" class="baldrick-refresh"><span class="icon-refresh"></span> ' . Text::_('PLG_KUNENA_BALDRICK_REFRESH_MODELS') . '</a>'
            . '</div></div>'
            . '<div class="baldrick-model-status small" style="margin-top:6px;" role="status" aria-live="polite"></div>'
            . '</div>';
    }
}

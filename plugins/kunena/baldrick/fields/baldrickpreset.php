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

use Joomla\Registry\Registry;

/**
 * The built-in master prompt (read only): the site context below is added to it.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class JFormFieldBaldrickPreset extends \Joomla\CMS\Form\FormField
{
    /**
     * @var string
     * @since 5.3.0
     */
    protected $type = 'BaldrickPreset';

    /**
     * @return  string
     *
     * @since 5.3.0
     */
    protected function getInput()
    {
        if (!class_exists('KunenaBaldrickPrompt')) {
            return '';
        }

        // The preset alone, without the site's context
        $saved = KunenaBaldrick::getParams();
        KunenaBaldrick::setParams(new Registry());
        $preset = KunenaBaldrickPrompt::answerSystem();
        KunenaBaldrick::setParams($saved);

        return '<textarea class="input-xxlarge" rows="10" readonly="readonly" id="' . htmlspecialchars($this->id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
            . htmlspecialchars($preset, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</textarea>';
    }
}

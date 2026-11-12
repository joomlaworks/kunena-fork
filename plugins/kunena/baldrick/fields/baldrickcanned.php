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

\Joomla\CMS\Form\FormHelper::loadFieldClass('textarea');

/**
 * Canned replies, filled with the built-in ones while empty.
 *
 * @since 5.3.0
 */
#[\AllowDynamicProperties]
class JFormFieldBaldrickCanned extends JFormFieldTextarea
{
    /**
     * @var string
     * @since 5.3.0
     */
    protected $type = 'BaldrickCanned';

    /**
     * @return  string
     *
     * @since 5.3.0
     */
    protected function getInput()
    {
        if (trim((string) $this->value) === '' && class_exists('KunenaBaldrickPrompt')) {
            $this->value = KunenaBaldrickPrompt::CANNED;
        }

        return parent::getInput();
    }
}

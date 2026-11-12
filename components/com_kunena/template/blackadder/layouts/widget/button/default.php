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

// Every action button of Kunena (created in its layout classes and controllers) is drawn here: the main actions as
// buttons, the rest as quiet text actions, with Bootstrap Icons chosen by the action's name.
$label = Text::_("COM_KUNENA_BUTTON_{$this->scope}_{$this->name}");
$title = Text::_("COM_KUNENA_BUTTON_{$this->scope}_{$this->name}_LONG");

if ($title == "COM_KUNENA_BUTTON_{$this->scope}_{$this->name}_LONG") {
    $title = '';
}

$icons = array(
    'create'      => 'plus-lg',
    'reply'       => 'reply',
    'quote'       => 'quote',
    'edit'        => 'pencil',
    'delete'      => 'trash',
    'permdelete'  => 'trash3',
    'undelete'    => 'arrow-counterclockwise',
    'moderate'    => 'arrows-move',
    'publish'     => 'check2-circle',
    'spam'        => 'exclamation-octagon',
    'thankyou'    => 'heart',
    'unthankyou'  => 'heart-fill',
    'subscribe'   => 'bell',
    'unsubscribe' => 'bell-slash',
    'favorite'    => 'star',
    'unfavorite'  => 'star-fill',
    'sticky'      => 'pin-angle',
    'unsticky'    => 'pin-angle-fill',
    'lock'        => 'lock-fill',
    'unlock'      => 'unlock',
    'markread'    => 'check2-all',
    'list'        => 'list',
    'report'      => 'flag',
    'quickreply'  => 'reply',
);

$main     = in_array($this->name, array('create', 'reply'), true) && $this->scope !== 'message';
$danger   = in_array($this->name, array('delete', 'permdelete', 'spam'), true);
$class    = $main ? 'kb-btn kb-btn--primary' : 'kb-action' . ($danger ? ' kb-action--danger' : '');
$template = KunenaFactory::getTemplate();
$icon     = isset($icons[$this->name]) ? $template->icon($icons[$this->name]) : '';
$id       = isset($this->id) ? ' id="' . $this->escape($this->id) . '"' : '';

// Permanent deletions ask first
$confirm = $this->name === 'permdelete' || $this->name === 'delete' && $this->scope === 'announcement'
    ? ' data-kb-confirm="' . $this->escape(Text::_('KUNENA_BLACKADDER_CONFIRM_DELETE')) . '"' : '';
?>
<a<?php echo $id; ?> class="<?php echo $class . ' kbutton-' . $this->escape($this->name); ?>" href="<?php echo $this->url; ?>" rel="nofollow"<?php echo $title !== '' ? ' title="' . $this->escape($title) . '"' : ''; ?><?php echo $confirm; ?>>
	<?php echo $icon; ?><span><?php echo $label; ?></span>
</a>

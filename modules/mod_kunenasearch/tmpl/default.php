<?php
/**
 * Kunena Search Module
 *
 * @package       Kunena.mod_kunenasearch
 *
 * @copyright (C) 2008 - 2026 Kunena Team. All rights reserved.
 * @license       http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link          https://www.kunena.org
 **/
defined('_JEXEC') or die();

// Module parameters are escaped for their context (HTML attribute, JavaScript string inside an attribute).
$ksearchTxt     = htmlspecialchars((string) $this->ksearch_txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$ksearchTxtJs   = htmlspecialchars(json_encode((string) $this->ksearch_txt, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$ksearchSfx     = htmlspecialchars((string) $this->ksearch_moduleclass_sfx, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$ksearchButton  = htmlspecialchars((string) $this->ksearch_button_txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<form action="<?php echo $this->url ?>" method="post" id="ksearch-form" name="ksearch-form">
	<input type="hidden" name="view" value="search" />
	<input type="hidden" name="task" value="results" />

	<div class="ksearch<?php echo $ksearchSfx; ?>">
		<fieldset class="ksearch-fieldset">
			<legend class="ksearch-legend"><?php echo JText::_('MOD_KUNENASEARCH_SEARCHBY_KEYWORD'); ?></legend>
			<label class="ksearch-label" for="ksearch-keywords"><?php echo JText::_('MOD_KUNENASEARCH_SEARCH_KEYWORDS'); ?>:</label>
			<input id="ksearch-keywords" type="text" class="ks kinput" name="q" size="<?php echo (int) $this->ksearch_width; ?>" value="<?php echo $ksearchTxt; ?>" onblur="if(this.value=='') this.value=<?php echo $ksearchTxtJs; ?>;" onfocus="if(this.value==<?php echo $ksearchTxtJs; ?>) this.value='';" />
			<input id="ksearch-keywordfilter" type="hidden" name="titleonly" value="0" />
			<?php
            if ($this->ksearch_button == 1) {
                if ($this->ksearch_button_pos == 'bottom') {
                    echo '<br />';
                }

                echo '<input type="submit" value="' . $ksearchButton . '" class="kbutton btn' . $ksearchSfx . '" />';
            };
?>
		</fieldset>
	</div>
</form>

<?php
/* Copyright (C) 2022 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    takeposconnector/lib/takeposconnector.lib.php
 * \ingroup takeposconnector
 * \brief   Library files with common functions for TakeposConnector
 */

/**
 * Prepare admin pages header: one tab for the common parameters, one per configured
 * terminal (both pointing to setup.php with a different ?scope=N), then About. Same head
 * array whichever page calls it (setup.php or about.php) — only the active tab differs.
 *
 * @return array
 */
function takeposconnectorAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("takeposconnector@takeposconnector");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/takeposconnector/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("TakeposconnCommonParameters");
	$head[$h][2] = 'scope0';
	$h++;

	for ($indexTerminal = 1; $indexTerminal <= getDolGlobalInt('TAKEPOS_NUM_TERMINALS'); $indexTerminal++) {
		$terminalLabel = $langs->trans('Terminal').' '.$indexTerminal;
		$terminalName = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$indexTerminal);
		if ($terminalName !== '') {
			$terminalLabel .= ': '.$terminalName;
		}

		$head[$h][0] = dol_buildpath("/takeposconnector/admin/setup.php", 1).'?scope='.$indexTerminal;
		$head[$h][1] = $terminalLabel;
		$head[$h][2] = 'scope'.$indexTerminal;
		$h++;
	}

	$head[$h][0] = dol_buildpath("/takeposconnector/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@takeposconnector:/takeposconnector/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@takeposconnector:/takeposconnector/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, null, $head, $h, 'takeposconnector@takeposconnector');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'takeposconnector@takeposconnector', 'remove');

	return $head;
}

/**
 * Get a terminal-aware configuration value.
 *
 * Each parameter has a common value stored under its bare name (e.g. DIRECTPRINTWHB_PORT)
 * and an optional per-terminal override stored under the suffixed name (e.g. DIRECTPRINTWHB_PORT2).
 * A terminal that has no override (constant absent or empty) inherits the common value.
 *
 * @param 	string 	$name 		Bare constant name (the common value)
 * @param 	int 	$terminal 	Terminal index (0 = no specific terminal, returns the common value)
 * @return 	string 				The override value if defined, otherwise the common value
 */
function takeposconnectorGetConf($name, $terminal = 0)
{
	global $conf;

	if ($terminal) {
		$override = $name.$terminal;
		if (isset($conf->global->$override) && $conf->global->$override !== '') {
			return $conf->global->$override;
		}
	}

	return isset($conf->global->$name) ? $conf->global->$name : '';
}

/**
 * Build (and add to $formSetup) the item for one parameter of the current scope: a plain
 * common-value field on the "Commun" tab (scope 0), or a terminal override widget (inherit
 * or specific value) on a "Terminal N" tab. Used by admin/setup.php for both the standalone
 * SSL field and every field of every device section.
 *
 * @param 	FormSetup 	$formSetup 		The setup form
 * @param 	Translate 	$langs 			Translations
 * @param 	int 		$scope 			Current scope (0 = common, N = terminal N)
 * @param 	string 		$scopeSuffix 	'' for scope 0, else (string) $scope
 * @param 	string 		$base 			Bare constant name
 * @param 	array 		$def 			Field definition (type/placeholder/css/mandatory/help/choices)
 * @return 	void
 */
function takeposconnectorSetupBuildItem($formSetup, $langs, $scope, $scopeSuffix, $base, $def)
{
	$key = $base.$scopeSuffix;

	if ($scope == 0) {
		// Common parameters: default value used by every terminal unless overridden.
		$item = $formSetup->newItem($key);
		$item->nameText = $langs->trans($base);
		if (!empty($def['css'])) {
			$item->cssClass = $def['css'];
		}
		if (!empty($def['placeholder'])) {
			$item->fieldAttr['placeholder'] = $def['placeholder'];
		}
		if (!empty($def['mandatory'])) {
			$item->fieldParams['isMandatory'] = 1;
		}
		if (!empty($def['help'])) {
			$item->helpText = $langs->trans($def['help']);
		}
		if ($def['type'] == 'select') {
			$item->setAsSelect($def['choices']);
		} elseif ($def['type'] == 'number') {
			$item->setAsNumber();
		}
	} else {
		// Terminal parameters: inherit the common value or define a specific one.
		$options = array();
		if (!empty($def['choices'])) {
			$options['choices'] = $def['choices'];
		}
		if (!empty($def['placeholder'])) {
			$options['placeholder'] = $def['placeholder'];
		}

		$item = $formSetup->newItem($key);
		$item->nameText = $langs->trans($base);
		$item->fieldInputOverride = takeposconnectorTerminalField($key, $base, $def['type'], $options);
		$item->setSaveCallBack('takeposconnectorSaveOverrideItem');
	}
}

/**
 * Build the edit-mode HTML for a per-terminal parameter that can either inherit the
 * common value or define a specific one. Renders a "specific value" checkbox followed
 * by the input widget, greyed out (disabled) as long as the terminal inherits the common value.
 *
 * Intended to be assigned to FormSetupItem::$fieldInputOverride.
 *
 * @param 	string 	$key 		Terminal constant name (e.g. DIRECTPRINTWHB_PORT2)
 * @param 	string 	$commonKey 	Common constant name (e.g. DIRECTPRINTWHB_PORT)
 * @param 	string 	$type 		Widget type: 'text', 'number' or 'select'
 * @param 	array 	$options 	Optional 'choices' (array for select) and 'placeholder'
 * @return 	string 				HTML for the value cell
 */
function takeposconnectorTerminalField($key, $commonKey, $type = 'text', $options = array())
{
	global $conf, $langs;

	$hasOverride = isset($conf->global->$key) && $conf->global->$key !== '';
	$commonVal = isset($conf->global->$commonKey) ? $conf->global->$commonKey : '';
	$value = $hasOverride ? $conf->global->$key : $commonVal;
	$disabled = $hasOverride ? '' : ' disabled';
	$fieldId = 'setup-'.$key;

	$overrideTitle = dol_escape_htmltag($langs->trans('TakeposconnSpecificValue'));
	$out = '<input type="checkbox" class="takeposconn-override-cb valignmiddle" name="'.$key.'_override" value="1" data-target="'.$fieldId.'" title="'.$overrideTitle.'"'.($hasOverride ? ' checked' : '').'> ';
	$out .= img_picto($langs->trans('TakeposconnSpecificValue'), 'help', '', false, 0, 0, '', 'paddingright').' ';

	$cssClass = 'flat minwidth200';
	if ($type == 'select') {
		$out .= '<select class="'.$cssClass.'" name="'.$key.'" id="'.$fieldId.'"'.$disabled.'>';
		foreach ($options['choices'] as $optkey => $optlabel) {
			$out .= '<option value="'.dol_escape_htmltag((string) $optkey).'"'.((string) $optkey === (string) $value ? ' selected' : '').'>'.dol_escape_htmltag($optlabel).'</option>';
		}
		$out .= '</select>';
	} else {
		$inputType = ($type == 'number') ? 'number' : 'text';
		$placeholder = empty($options['placeholder']) ? '' : ' placeholder="'.dol_escape_htmltag($options['placeholder']).'"';
		$out .= '<input type="'.$inputType.'" class="'.$cssClass.'" name="'.$key.'" id="'.$fieldId.'" value="'.dol_escape_htmltag((string) $value).'"'.$placeholder.$disabled.'>';
	}

	return $out;
}

/**
 * Shared JavaScript that enables/disables the per-terminal input when its
 * "specific value" checkbox is toggled. Print once, after the setup form.
 *
 * @return 	string 	The <script> block
 */
function takeposconnectorOverrideJs()
{
	return '<script>
	jQuery(document).ready(function() {
		jQuery(".takeposconn-override-cb").on("change", function() {
			var field = jQuery("#" + jQuery(this).data("target"));
			if (jQuery(this).is(":checked")) {
				field.prop("disabled", false).focus();
			} else {
				field.prop("disabled", true);
			}
		});
	});
	</script>';
}

/**
 * FormSetup save callback for a per-terminal override item. Stores the value as a
 * specific override when the "specific value" checkbox is ticked (and not empty),
 * otherwise deletes the constant so the terminal inherits the common value.
 *
 * @param 	FormSetupItem 	$item 	The setup item being saved
 * @return 	int 					1 if OK, -1 if KO
 */
function takeposconnectorSaveOverrideItem($item)
{
	global $db, $conf;

	$key = $item->confKey;
	$override = GETPOST($key.'_override', 'int');
	$value = GETPOST($key, 'alphanohtml');

	if ($override && $value !== '') {
		$res = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
	} else {
		$res = dolibarr_del_const($db, $key, $conf->entity);
	}

	return ($res < 0) ? -1 : 1;
}

/**
 * CSS for the setup page: keeps the title/field column widths consistent across every table
 * (one per device section, see admin/setup.php). No JS needed: switching scope (common
 * parameters / terminal N) is a real Dolibarr tab built by takeposconnectorAdminPrepareHead(),
 * not a client-side toggle — each scope is its own page load.
 *
 * @return 	string 	The <style> block
 */
function takeposconnectorSetupStyle()
{
	return <<<'HTML'
<style>
.takeposconn-setup table.noborder { margin-top:0; }
.takeposconn-setup .col-setup-title { width:400px; max-width:400px; }
.takeposconn-setup input[type="text"],
.takeposconn-setup input[type="number"],
.takeposconn-setup select { width:400px; min-width:0; max-width:100%; box-sizing:border-box; }
</style>
HTML;
}

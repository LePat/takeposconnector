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
 * Prepare admin pages header
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
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	/*
	$head[$h][0] = dol_buildpath("/takeposconnector/admin/myobject_extrafields.php", 1);
	$head[$h][1] = $langs->trans("ExtraFields");
	$head[$h][2] = 'myobject_extrafields';
	$h++;
	*/

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
 * Insert a non-tabbable subsection header row into a FormSetup (styled via CSS, not a real
 * FormSetup title: a title() row would be picked up by takeposconnectorTabsScript() as a new
 * top-level tab, which would break the Common/Terminal-N tab structure).
 *
 * @param 	FormSetup 	$formSetup 	The setup form
 * @param 	Translate 	$langs 		Translations
 * @param 	string 		$key 		Unique confKey for this header
 * @param 	string 		$labelKey 	Lang key of the section label
 * @return 	void
 */
function takeposconnectorSetupSectionHeader($formSetup, $langs, $key, $labelKey)
{
	$item = $formSetup->newItem($key);
	$item->nameText = $langs->trans($labelKey);
	$item->fieldOverride = '&nbsp;';
	$item->fieldParams['trClass'] = 'takeposconn-subsection';
}

/**
 * Build the <style>/<script> block that turns the FormSetup section titles
 * (rendered by generateOutput() as <tr class="liste_titre"> rows: the "common
 * parameters" section then one section per terminal) into in-page tabs.
 *
 * The setup stays a single form with a single Save button: switching tabs only
 * shows/hides rows client-side, nothing is submitted on tab change, and hidden
 * inputs are still posted on Save. Must be printed right after the FormSetup
 * output, which must be wrapped in a <div id="takeposconn-tabbed-setup">.
 *
 * @return 	string 	The <style> + <script> block
 */
function takeposconnectorTabsScript()
{
	return <<<'HTML'
<style>
#takeposconn-tabbed-setup .takeposconn-tabs { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:3px; }
#takeposconn-tabbed-setup .takeposconn-tabs li { padding:6px 14px; cursor:pointer; border:1px solid var(--colortopbordertitle1, #ccc); border-bottom:none; border-radius:5px 5px 0 0; background:var(--colorbacktitle1, #f4f4f4); white-space:nowrap; }
#takeposconn-tabbed-setup .takeposconn-tabs li.active { background:var(--colorbackbody, #fff); font-weight:bold; }
#takeposconn-tabbed-setup table.noborder { margin-top:0; }
#takeposconn-tabbed-setup .col-setup-title { width:400px; max-width:400px; }
#takeposconn-tabbed-setup input[type="text"],
#takeposconn-tabbed-setup input[type="number"],
#takeposconn-tabbed-setup select { width:400px; min-width:0; max-width:100%; box-sizing:border-box; }
#takeposconn-tabbed-setup tr.takeposconn-subsection td { font-weight:bold; padding-top:12px; border-top:1px solid var(--colortopbordertitle1, #ccc); }
</style>
<script>
jQuery(document).ready(function() {
	var $root = jQuery("#takeposconn-tabbed-setup");
	var $table = $root.find("table").first();
	var $titles = $table.find("tbody > tr.liste_titre");
	if ($titles.length < 2) { return; }

	var $nav = jQuery('<ul class="takeposconn-tabs"></ul>');
	$titles.each(function(i) {
		var $title = jQuery(this);
		$title.addClass("takeposconn-sec-title").attr("data-sec", i).hide();
		$title.nextUntil("tr.liste_titre").attr("data-sec", i);
		var label = jQuery.trim($title.find("td").first().text());
		$nav.append(jQuery('<li></li>').attr("data-sec", i).text(label));
	});
	$root.find(".div-table-responsive-no-min").first().before($nav);

	function showSection(sec) {
		$nav.find("li").removeClass("active").filter('[data-sec="' + sec + '"]').addClass("active");
		$table.find("tbody > tr").hide();
		$table.find('tbody > tr[data-sec="' + sec + '"]').not(".takeposconn-sec-title").show();
	}
	$nav.on("click", "li", function() { showSection(jQuery(this).data("sec")); });
	showSection(0);
});
</script>
HTML;
}

<?php
/* Copyright (C) 2004-2017 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2022 SuperAdmin
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
 * \file    takeposconnector/admin/setup.php
 * \ingroup takeposconnector
 * \brief   TakeposConnector setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user;

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/takeposconnector.lib.php';
//require_once "../class/myclass.class.php";

// Translations
$langs->loadLangs(array("admin", "takeposconnector@takeposconnector"));

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$hookmanager->initHooks(array('takeposconnectorsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
	accessforbidden();
}

// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Select printer to use with terminal
if(file_exists(DOL_DOCUMENT_ROOT.'/takepos/class/dolreceiptprinter.class.php')) {
	require_once DOL_DOCUMENT_ROOT.'/takepos/class/dolreceiptprinter.class.php';
} else {
	require_once DOL_DOCUMENT_ROOT.'/core/class/dolreceiptprinter.class.php';
}
$printer = new dolReceiptPrinter($db);

$printer->listPrintersTemplates();
$templates = array();
foreach ($printer->listprinterstemplates as $key => $value) {
	$templates[$value['rowid']] = $value['name'];
}

$TField = array(
	'none' => $langs->trans('NONE'),
	'diag06' => $langs->trans('Dialog-06'),
);
$fieldOptions = array(
	'oui' => $langs->trans('OUI'),
	'non' => $langs->trans('NON'),
);

// Definition of every parameter that has a common value and can be overridden per terminal.
// 'type' drives both the common widget and the per-terminal override widget.
$paramDefs = array(
	'WEIGHINGSCALE_WEBSOCKET_URL'          => array('type' => 'text',   'placeholder' => 'ws://localhost:12212/serial/WEIGH', 'css' => 'minwidth500', 'mandatory' => 1, 'help' => 'URL du WebSocket configuré sur le Webapp-Hardware-Bridge pour effectuer les pesées'),
	'WEIGHINGSCALE_PROTOCOL'               => array('type' => 'select', 'choices' => $TField),
	'CUSTOMERDISPLAY_WEBSOCKET_URL'        => array('type' => 'text',   'placeholder' => 'ws://localhost:12212/serial/DISPLAY', 'css' => 'minwidth500', 'mandatory' => 1, 'help' => 'URL du WebSocket configuré sur le Webapp-Hardware-Bridge pour l\'afficheur client'),
	'DIRECTPRINTWHB_SECURE'                => array('type' => 'select', 'choices' => $fieldOptions),
	'DIRECTPRINTWHB_IPADDRESS'             => array('type' => 'text',   'placeholder' => 'localhost'),
	'DIRECTPRINTWHB_PORT'                  => array('type' => 'number', 'placeholder' => '12212'),
	'DIRECTPRINTWHB_PRINTER_SERVICE_NAME'  => array('type' => 'text',   'placeholder' => '/printer'),
	'DIRECTPRINTWHB_TPPRINTERID'           => array('type' => 'text',   'placeholder' => 'INVOICE'),
	'TAKEPOS_TEMPLATE_TO_USE_FOR_INVOICES' => array('type' => 'select', 'choices' => $templates),
	'TAKEPOS_INVOICE_LINE_DESC_MAX_LENGTH' => array('type' => 'number'),
	'RECEIPT_PRINTER_NB_CHARACT_BY_LINE'   => array('type' => 'number'),
);

// ----- Common parameters (default value used by every terminal unless overridden) -----
$item = $formSetup->newItem('TakeposconnCommonParametersTitle')->setAsTitle();
$item->nameText = $langs->trans('TakeposconnCommonParameters');

foreach ($paramDefs as $base => $def) {
	$item = $formSetup->newItem($base);
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
		$item->helpText = $def['help'];
	}
	if ($def['type'] == 'select') {
		$item->setAsSelect($def['choices']);
	} elseif ($def['type'] == 'number') {
		$item->setAsNumber();
	}
}

// ----- Per-terminal parameters (inherit the common value or define a specific one) -----
for ($indexTerminal = 1; $indexTerminal <= getDolGlobalInt('TAKEPOS_NUM_TERMINALS'); $indexTerminal++) {
	$item = $formSetup->newItem('Terminal'.$indexTerminal)->setAsTitle();
	$item->nameText = $langs->trans('Terminal').' '.$indexTerminal;
	$terminalName = getDolGlobalString('TAKEPOS_TERMINAL_NAME_'.$indexTerminal);
	if ($terminalName !== '') {
		$item->nameText .= ': '.$terminalName;
	}

	foreach ($paramDefs as $base => $def) {
		$key = $base.$indexTerminal;
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


$setupnotempty = count($formSetup->items);


/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'alpha');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'specimen') {
	$modele = GETPOST('module', 'alpha');
	$tmpobjectkey = GETPOST('object');

	$tmpobject = new $tmpobjectkey($db);
	$tmpobject->initAsSpecimen();

	// Search template files
	$file = ''; $classname = ''; $filefound = 0;
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir) {
		$file = dol_buildpath($reldir."core/modules/takeposconnector/doc/pdf_".$modele."_".strtolower($tmpobjectkey).".modules.php", 0);
		if (file_exists($file)) {
			$filefound = 1;
			$classname = "pdf_".$modele;
			break;
		}
	}

	if ($filefound) {
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($tmpobject, $langs) > 0) {
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=".strtolower($tmpobjectkey)."&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
} elseif ($action == 'setmod') {
	// TODO Check if numbering module chosen can be activated by calling method canBeActivated
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'TAKEPOSCONNECTOR_'.strtoupper($tmpobjectkey)."_ADDON";
		dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
	}
} elseif ($action == 'set') {
	// Activate a model
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0) {
		$tmpobjectkey = GETPOST('object');
		if (!empty($tmpobjectkey)) {
			$constforval = 'TAKEPOSCONNECTOR_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
			if ($conf->global->$constforval == "$value") {
				dolibarr_del_const($db, $constforval, $conf->entity);
			}
		}
	}
} elseif ($action == 'setdoc') {
	// Set or unset default model
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'TAKEPOSCONNECTOR_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		if (dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity)) {
			// The constant that was read before the new set
			// We therefore requires a variable to have a coherent view
			$conf->global->$constforval = $value;
		}

		// We disable/enable the document template (into llx_document_model table)
		$ret = delDocumentModel($value, $type);
		if ($ret > 0) {
			$ret = addDocumentModel($value, $type, $label, $scandir);
		}
	}
} elseif ($action == 'unsetdoc') {
	$tmpobjectkey = GETPOST('object');
	if (!empty($tmpobjectkey)) {
		$constforval = 'TAKEPOSCONNECTOR_'.strtoupper($tmpobjectkey).'_ADDON_PDF';
		dolibarr_del_const($db, $constforval, $conf->entity);
	}
}

$action = 'edit';

/*
 * View
 */

$form = new Form($db);

$help_url = '';
$page_name = "TakeposConnectorSetup";

llxHeader('', $langs->trans($page_name), $help_url);

// Subheader
$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans($page_name), $linkback, 'title_setup');

// Configuration header
$head = takeposconnectorAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($page_name), -1, "takeposconnector@takeposconnector");

// Setup page goes here
echo '<span class="opacitymedium">'.$langs->trans("TakePOS Connector").'</span><br><br>';

echo '<h1><a target="_blank" href="https://github.com/imTigger/webapp-hardware-bridge/releases">Download TakePOS connector client APP</a></h1>';


if ($action == 'edit') {
	if ($useFormSetup && (float) DOL_VERSION >= 15) {
		print '<div id="takeposconn-tabbed-setup">';
		print $formSetup->generateOutput(true, true);
		print '</div>';
		print takeposconnectorOverrideJs();
		print takeposconnectorTabsScript();
	} else {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="update">';

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

		foreach ($arrayofparameters as $constname => $val) {
			if ($val['enabled']==1) {
				$setupnotempty++;
				print '<tr class="oddeven"><td>';
				$tooltiphelp = (($langs->trans($constname . 'Tooltip') != $constname . 'Tooltip') ? $langs->trans($constname . 'Tooltip') : '');
				print '<span id="helplink'.$constname.'" class="spanforparamtooltip">'.$form->textwithpicto($langs->trans($constname), $tooltiphelp, 1, 'info', '', 0, 3, 'tootips'.$constname).'</span>';
				print '</td><td>';

				if ($val['type'] == 'textarea') {
					print '<textarea class="flat" name="'.$constname.'" id="'.$constname.'" cols="50" rows="5" wrap="soft">' . "\n";
					print $conf->global->{$constname};
					print "</textarea>\n";
				} elseif ($val['type']== 'html') {
					require_once DOL_DOCUMENT_ROOT . '/core/class/doleditor.class.php';
					$doleditor = new DolEditor($constname, $conf->global->{$constname}, '', 160, 'dolibarr_notes', '', false, false, $conf->fckeditor->enabled, ROWS_5, '90%');
					$doleditor->Create();
				} elseif ($val['type'] == 'yesno') {
					print $form->selectyesno($constname, $conf->global->{$constname}, 1);
				} elseif (preg_match('/emailtemplate:/', $val['type'])) {
					include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
					$formmail = new FormMail($db);

					$tmp = explode(':', $val['type']);
					$nboftemplates = $formmail->fetchAllEMailTemplate($tmp[1], $user, null, 1); // We set lang=null to get in priority record with no lang
					//$arraydefaultmessage = $formmail->getEMailTemplate($db, $tmp[1], $user, null, 0, 1, '');
					$arrayofmessagename = array();
					if (is_array($formmail->lines_model)) {
						foreach ($formmail->lines_model as $modelmail) {
							//var_dump($modelmail);
							$moreonlabel = '';
							if (!empty($arrayofmessagename[$modelmail->label])) {
								$moreonlabel = ' <span class="opacitymedium">(' . $langs->trans("SeveralLangugeVariatFound") . ')</span>';
							}
							// The 'label' is the key that is unique if we exclude the language
							$arrayofmessagename[$modelmail->id] = $langs->trans(preg_replace('/\(|\)/', '', $modelmail->label)) . $moreonlabel;
						}
					}
					print $form->selectarray($constname, $arrayofmessagename, $conf->global->{$constname}, 'None', 0, 0, '', 0, 0, 0, '', '', 1);
				} elseif (preg_match('/category:/', $val['type'])) {
					require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
					require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
					$formother = new FormOther($db);

					$tmp = explode(':', $val['type']);
					print img_picto('', 'category', 'class="pictofixedwidth"');
					print $formother->select_categories($tmp[1],  $conf->global->{$constname}, $constname, 0, $langs->trans('CustomersProspectsCategoriesShort'));
				} elseif (preg_match('/thirdparty_type/', $val['type'])) {
					require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
					$formcompany = new FormCompany($db);
					print $formcompany->selectProspectCustomerType($conf->global->{$constname}, $constname);
				} elseif ($val['type'] == 'securekey') {
					print '<input required="required" type="text" class="flat" id="'.$constname.'" name="'.$constname.'" value="'.(GETPOST($constname, 'alpha') ?GETPOST($constname, 'alpha') : $conf->global->{$constname}).'" size="40">';
					if (!empty($conf->use_javascript_ajax)) {
						print '&nbsp;'.img_picto($langs->trans('Generate'), 'refresh', 'id="generate_token'.$constname.'" class="linkobject"');
					}
					if (!empty($conf->use_javascript_ajax)) {
						print "\n".'<script type="text/javascript">';
						print '$(document).ready(function () {
                        $("#generate_token'.$constname.'").click(function() {
                	        $.get( "'.DOL_URL_ROOT.'/core/ajax/security.php", {
                		      action: \'getrandompassword\',
                		      generic: true
    				        },
    				        function(token) {
    					       $("#'.$constname.'").val(token);
            				});
                         });
                    });';
						print '</script>';
					}
				} elseif ($val['type'] == 'product') {
					if (!empty($conf->product->enabled) || !empty($conf->service->enabled)) {
						$selected = (empty($conf->global->$constname) ? '' : $conf->global->$constname);
						$form->select_produits($selected, $constname, '', 0);
					}
				} else {
					print '<input name="'.$constname.'"  class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.$conf->global->{$constname}.'">';
				}
				print '</td></tr>';
			}
		}
		print '</table>';

		print '<br><div class="center">';
		print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
		print '</div>';

		print '</form>';
	}

	print '<br>';
} else {
	if ($useFormSetup && (float) DOL_VERSION >= 15) {
		if (!empty($formSetup->items)) {
			print $formSetup->generateOutput();
		}
	} else {
		if (!empty($arrayofparameters)) {
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

			foreach ($arrayofparameters as $constname => $val) {
				if ($val['enabled']==1) {
					$setupnotempty++;
					print '<tr class="oddeven"><td>';
					$tooltiphelp = (($langs->trans($constname . 'Tooltip') != $constname . 'Tooltip') ? $langs->trans($constname . 'Tooltip') : '');
					print $form->textwithpicto($langs->trans($constname), $tooltiphelp);
					print '</td><td>';

					if ($val['type'] == 'textarea') {
						print dol_nl2br($conf->global->{$constname});
					} elseif ($val['type']== 'html') {
						print  $conf->global->{$constname};
					} elseif ($val['type'] == 'yesno') {
						print ajax_constantonoff($constname);
					} elseif (preg_match('/emailtemplate:/', $val['type'])) {
						include_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
						$formmail = new FormMail($db);

						$tmp = explode(':', $val['type']);

						$template = $formmail->getEMailTemplate($db, $tmp[1], $user, $langs, $conf->global->{$constname});
						if ($template<0) {
							setEventMessages(null, $formmail->errors, 'errors');
						}
						print $langs->trans($template->label);
					} elseif (preg_match('/category:/', $val['type'])) {
						$c = new Categorie($db);
						$result = $c->fetch($conf->global->{$constname});
						if ($result < 0) {
							setEventMessages(null, $c->errors, 'errors');
						} elseif ($result > 0 ) {
							$ways = $c->print_all_ways(' &gt;&gt; ', 'none', 0, 1); // $ways[0] = "ccc2 >> ccc2a >> ccc2a1" with html formated text
							$toprint = array();
							foreach ($ways as $way) {
								$toprint[] = '<li class="select2-search-choice-dolibarr noborderoncategories"' . ($c->color ? ' style="background: #' . $c->color . ';"' : ' style="background: #bbb"') . '>' . $way . '</li>';
							}
							print '<div class="select2-container-multi-dolibarr" style="width: 90%;"><ul class="select2-choices-dolibarr">' . implode(' ', $toprint) . '</ul></div>';
						}
					} elseif (preg_match('/thirdparty_type/', $val['type'])) {
						if ($conf->global->{$constname}==2) {
							print $langs->trans("Prospect");
						} elseif ($conf->global->{$constname}==3) {
							print $langs->trans("ProspectCustomer");
						} elseif ($conf->global->{$constname}==1) {
							print $langs->trans("Customer");
						} elseif ($conf->global->{$constname}==0) {
							print $langs->trans("NorProspectNorCustomer");
						}
					} elseif ($val['type'] == 'product') {
						$product = new Product($db);
						$resprod = $product->fetch($conf->global->{$constname});
						if ($resprod > 0) {
							print $product->ref;
						} elseif ($resprod < 0) {
							setEventMessages(null, $object->errors, "errors");
						}
					} else {
						print $conf->global->{$constname};
					}
					print '</td></tr>';
				}
			}

			print '</table>';
		}
	}

	if ($setupnotempty) {
		print '<div class="tabsAction">';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
		print '</div>';
	} else {
		print '<br>'.$langs->trans("NothingToSetup");
	}
}


if (empty($setupnotempty)) {
	print '<br>'.$langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();

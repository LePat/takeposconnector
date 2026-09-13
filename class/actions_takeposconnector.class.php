<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2025		pat <info@lia-concept.fr>
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
 * \file    takeposconnector/class/actions_takeposconnector.class.php
 * \ingroup takeposconnector
 * \brief   Hook overload: injects the module's JS/CSS into the TakePOS frontend
 *          (addHtmlHeader) and exposes fk_unit/price on rendered product tiles
 *          so TakePOS can auto-trigger the weighing scale (completeJSProductDisplay).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

/**
 * Class ActionsTakeposConnector
 */
class ActionsTakeposConnector extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * Constructor
	 *
	 *  @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Inject the module's JS/CSS into the TakePOS frontend page header.
	 *
	 * @param  array          $parameters   Hook parameters
	 * @param  CommonObject   $object       Hooked object
	 * @param  string         $action       Current action
	 * @param  HookManager    $hookmanager  Hook manager
	 * @return void
	 */
	public function addHtmlHeader($parameters, &$object, &$action, $hookmanager)
	{
		// 'takeposfrontend' = index.php (page principale). 'takepospay' = pay.php, qui
		// s'ouvre dans sa PROPRE iframe ($.colorbox({..., iframe:"true"}) dans
		// index.php:701) : ce n'est pas le même document/scope JS que index.php, donc
		// webSocketCustomerDisplay/webSocketWeight (déclarés par ce script, référencés
		// directement par pay.php core) doivent y être redéclarés. invoice.php, lui, est
		// injecté en ajax dans le DOM d'index.php (#poslines.load(...)) : il partage déjà
		// son scope JS et n'a pas besoin de sa propre inclusion.
		if (in_array($parameters['currentcontext'], array('takeposfrontend', 'takepospay'))) {
			$this->resprints = '<script src="' . DOL_URL_ROOT . '/custom/takeposconnector/js/takeposconnector.js.php"></script>' . PHP_EOL;
			$this->resprints .= '<script src="' . DOL_URL_ROOT . '/custom/takeposconnector/js/dialog06-protocol.js"></script>' . PHP_EOL;
			$this->resprints .= '<link rel="stylesheet" type="text/css" href="' . DOL_URL_ROOT . '/custom/takeposconnector/css/takeposconnector.css">' . PHP_EOL;
		}
	}

	/**
	 * Expose the product unit on rendered product tiles, so TakePOS can determine
	 * whether the product must be weighed.
	 *
	 * @param  array          $parameters   Hook parameters
	 * @param  CommonObject   $object       Hooked object
	 * @param  string         $action       Current action
	 * @param  HookManager    $hookmanager  Hook manager
	 * @return void
	 */
	public function completeJSProductDisplay($parameters, &$object, &$action, $hookmanager)
	{
		if ($parameters['caller'] == 'loadProducts') {
			$term = empty($_SESSION['takeposterminal']) ? 1 : $_SESSION['takeposterminal'];
			$socid = getDolGlobalInt('CASHDESK_ID_THIRDPARTY' . $term);

			$priceLevel = 0;
			if ($socid > 0) {
				global $db;
				$customer = new Societe($db);
				if ($customer->fetch($socid) > 0) {
					$priceLevel = $customer->price_level;
				}
			}

			if ($priceLevel) {
				$this->resprints = '
					$("#prodiv"+ishow).data("unit", data[idata][\'fk_unit\']);
					$("#prodiv"+ishow).data("price-ttc", data[idata][\'multiprices_ttc\'][' . $customer->price_level . ']);
				';
			} else {
				$this->resprints = '
					$("#prodiv"+ishow).data("unit", data[idata][\'fk_unit\']);
					$("#prodiv"+ishow).data("price-ttc", data[idata][\'price_ttc\']);
				';
			}
		}
	}
}

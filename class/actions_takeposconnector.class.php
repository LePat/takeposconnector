<?php
/* Copyright (C) 2023		Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2025		SuperAdmin
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
 * \file    takeposasorder/class/actions_takeposasorder.class.php
 * \ingroup takeposasorder
 * \brief   Example hook overload.
 *
 * TODO: Write detailed description here.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsTakePOSAsOrder
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
	
	public function addHtmlHeader($parameters, &$object, &$action, $hookmanager) {
		if ($parameters['currentcontext'] == 'takeposfrontend') {
			$this->resprints = '<script src="' . DOL_URL_ROOT . '/custom/takeposconnector/js/takeposconnector.js.php"></script>';
			$this->resprints .= PHP_EOL . '<script src="' . DOL_URL_ROOT . '/custom/takeposconnector/js/dialog06-protocol.js"></script>';
		}
	}
	
	/**
	 * Rendre l'unité des produits disponible pour déterminer s'il faut les peser ou non.
	 * 
	 * @param $parameters
	 * @param $object
	 * @param $action
	 * @param $hookmanager
	 */
	public function completeJSProductDisplay($parameters, &$object, &$action, $hookmanager) {
	    if ($parameters['caller'] == 'loadProducts') {
	        $this->resprints = '
				$("#prodiv"+ishow).data("unit", data[idata][\'fk_unit\']);
		';
	    }
	}
}

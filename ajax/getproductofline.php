<?php
/* Copyright (C) 2024-2026  pat  <info@lia-concept.fr>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/custom/takeposconnector/ajax/getproductofline.php
 *  \brief      Ajax endpoint returning the product attached to an invoice line.
 *              Replaces the REST call
 *              GET /api/index.php/takeposconnector/invoices/{id}/lines/{lineid}/product
 *              which leaked $user->api_key into the HTML rendered by
 *              htdocs/takepos/index.php (WeighingScale, protocol diag06).
 *              Uses session auth + CSRF token (currentToken()) instead.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOBROWSERNOTIF')) {
	define('NOBROWSERNOTIF', '1');
}
if (!defined('CSRFCHECK_WITH_TOKEN')) {
	define('CSRFCHECK_WITH_TOKEN', '1'); // Token is required even on GET
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

$invoiceid = GETPOSTINT('invoiceid');
$lineid = GETPOSTINT('lineid');

if (empty($user->id) || !$user->hasRight('takepos', 'run') || !$user->hasRight('facture', 'lire')) {
	httponly_accessforbidden('Forbidden', 403);
}

top_httphead('application/json');

try {
	if (empty($invoiceid) || empty($lineid)) {
		http_response_code(400);
		echo json_encode(array('error' => 'invoiceid and lineid are mandatory'));
		exit;
	}

	$invoice = new Facture($db);
	if ($invoice->fetch($invoiceid) <= 0) {
		http_response_code(404);
		echo json_encode(array('error' => 'Invoice not found'));
		exit;
	}

	$invoice->getLinesArray();
	foreach ($invoice->lines as $line) {
		if ($line->id == $lineid) {
			$product = new Product($db);
			if ($product->fetch($line->fk_product) <= 0) {
				http_response_code(404);
				echo json_encode(array('error' => 'Product not found'));
				exit;
			}
			// Only the fields consumed by TakePOS's weighing scale JS (fk_unit + price)
			// are returned: the full Product object also carries cost prices, margins,
			// accountancy codes, supplier data, etc. that this endpoint has no reason to expose.
			echo json_encode(array(
				'id' => $product->id,
				'ref' => $product->ref,
				'label' => $product->label,
				'fk_unit' => $product->fk_unit,
				'price_ttc' => $product->price_ttc,
				'multiprices_ttc' => $product->multiprices_ttc,
			));
			exit;
		}
	}

	http_response_code(404);
	echo json_encode(array('error' => 'Line not found in invoice'));
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(array('error' => $e->getMessage()));
}

<?php
/* Copyright (C) 2015   	Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2016		Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
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

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

/**
 * API class for TakePOSConnector
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class TakePOSConnector extends DolibarrApi {
	
	/**
	 * @var Facture {@type Facture}
	 */
	private $invoice;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->invoice = new Facture($this->db);
    }
    
    /**
     * Get properties of a line of an invoice by invoice id and line id
     *
     * Return an array with product information.
     *
     * @param  int    $id                  ID of invoice
     * @param  int    $lineid              ID of invoice line
     * @return array|mixed                 Data without useless information
     * 
     * @url     GET /invoices/{id}/lines/{lineid}/product
     *
     * @throws RestException 401
     * @throws RestException 403
     * @throws RestException 404
     */
    public function getProductOfLineOfInvoice($id, $lineid) {
    	if (!DolibarrApiAccess::$user->hasRight('facture', 'lire')) {
    		throw new RestException(403);
    	}
    	
    	$result = $this->invoice->fetch($id);
    	if (!$result) {
    		throw new RestException(404, 'Invoice not found');
    	}
    	
    	if (!DolibarrApi::_checkAccessToResource('facture', $this->invoice->id)) {
    		throw new RestException(403, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
    	}
    	$this->invoice->getLinesArray();
    	foreach ($this->invoice->lines as $line) {
    		if ($line->id == $lineid) {
    			$product = new Product($this->db);
    			$product->fetch($line->fk_product);
    			// Only the fields consumed by TakePOS's weighing scale JS (fk_unit + price)
    			// are returned: the full Product object also carries cost prices, margins,
    			// accountancy codes, supplier data, etc. that this endpoint has no reason to expose.
    			return array(
    				'id' => $product->id,
    				'ref' => $product->ref,
    				'label' => $product->label,
    				'fk_unit' => $product->fk_unit,
    				'price_ttc' => $product->price_ttc,
    				'multiprices_ttc' => $product->multiprices_ttc,
    			);
    		}
    	}
    	return;
    }
}

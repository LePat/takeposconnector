<?php
/* Copyright (C) 2022 Catriel Rios <catriel_r@hotmail.com>
 * Copyright (C) 2022 Andreu Bisquerra <jove@bisquerra.com>
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
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER')) {
	define('NOREQUIREUSER', '1');
}
//if (!defined('NOREQUIREDB')) {
//	define('NOREQUIREDB', '1');
//}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
//if (!defined('NOREQUIRETRAN')) {
//	define('NOREQUIRETRAN', '1');
//}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}


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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
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

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) {
	header('Cache-Control: max-age=3600, public, must-revalidate');
} else {
	header('Cache-Control: no-cache');
}

$refer = '';
if (isset($_SERVER['HTTP_REFERER'])) {
	$refer = $_SERVER['HTTP_REFERER'];
}
if (empty($refer) || preg_match('/takepos\/index.php/', $refer)) {
	$terminaltouse = 0;
	if ($_SESSION["takeposterminal"]) {
		$terminaltouse = $_SESSION["takeposterminal"];
	}
}
if (empty($refer) || preg_match('/compta\/facture\/card.php/', $refer)) {
	$terminaltouse = 0;
}

global $conf, $langs;

$ws = 'ws://';
if ($conf->global->{'DIRECTPRINTWHB_SECURE'.$terminaltouse}) {
	$ws = 'wss://';
}

?>

// ===============================================
// WebSocketSerial (defaults to CustomerDisplay)
// ===============================================

function WebSocketSerial(options) {
    var defaults = {
        url: 'ws://localhost:12212/serial/DISPLAY',
        onConnect: function () {
        },
        onDisconnect: function () {
        },
        onMessage: function (message) {
        }
    };

    var settings = Object.assign({}, defaults, options);
    var websocket;
    var buffer = '';

    var onMessage = function (evt) {
        var chr = evt.data;
        settings.onMessage(chr);
    };

    var onConnect = function () {
        settings.onConnect();
    };

    var onDisconnect = function () {
        settings.onDisconnect();
        reconnect();
    };

    var connect = function () {
        websocket = new WebSocket(settings.url);
        websocket.onopen = onConnect;
        websocket.onclose = onDisconnect;
        websocket.onmessage = onMessage;
    };

    var reconnect = function () {
        connect();
    };
	
	this.readyState = function () {
		return websocket.readyState;
	};
	
	this.onOpen = function(callback) {
		websocket.onopen = callback;
	};

    this.send = function (message) {
        websocket.send(message);
    };

    connect();
}

// Make it available
const webSocketCustomerDisplay = new WebSocketSerial({
	url: '<?php echo $conf->global->CUSTOMERDISPLAY_WEBSOCKET_URL; ?>'
});
	
// ===============================================
// WebSocketWeigh (default protocol)
// ===============================================

function WebSocketWeigh(options) {
    var defaults = {
        url: 'ws://localhost:12212/serial/WEIGH',
        weightRegex: new RegExp('([0-9]{1,2}\\.[0-9]{3})kg'),
        stableRegex: new RegExp('^ST.*\\s+'),
        onConnect: function () {
        },
        onDisconnect: function () {
        },
        onUpdate: function (weight, stable) {
        }
    };

    var settings = Object.assign({}, defaults, options);
    var websocket;
    var buffer = '';

    var onMessage = function (evt) {
        var chr = evt.data;
		console.log("data: " + chr);

		<?php if ($conf->global->WEIGHINGSCALE_PROTOCOL == "diag06") { ?>

			var response = CheckoutDialog06.identifyMessage(evt.data);
			if (response.type == 'ACK' && currentStateClient == ClientStates.SENDING_UNITPRICE_BEFORE_WEIGHING) {
				currentStateClient = ClientStates.ACK_RECEIVED_FOR_UNITPRICE;
				console.log(currentStateClient);
				ENQ();
			}
			if (response.type == 'NAK' && currentStateClient == ClientStates.REQUESTED_WEIGHING_SCALE) {
				webSocketTakePOS.send(CheckoutDialog06.formatMessage(CheckoutDialog06.createRecord08()));
			}
			if (response.type == 'RECORD_09' && currentStateClient == ClientStates.REQUESTED_WEIGHING_SCALE) {
				// Poids inchangé depuis la dernière pesée
				if (response.data.statusCode == '21') {
					currentStateClient = ClientStates.WAITING_FOR_COMMAND;
					console.log(currentStateClient);
					currentCallback(currentWeight);
				}
				// Signaler l'erreur à l'utilisateur 
				// TODO demander de saisir le poids
				currentErrorCallback(response.data.errorMessage);
			}
			if (response.type == 'RECORD_02' && currentStateClient == ClientStates.REQUESTED_WEIGHING_SCALE) {
				currentWeight = response.data.weight;
				currentStateClient = ClientStates.WAITING_FOR_COMMAND;
				console.log(currentStateClient);
				currentCallback(currentWeight);
			}
			if (response.type == 'RECORD_11' && currentStateClient == ClientStates.REQUESTED_WEIGHING_SCALE) {
				currentStateClient = ClientStates.CHECKSUM;
				console.log(currentStateClient);
				checksum = response.data.randomNumber.charAt(0);
				correctionValue = response.data.randomNumber.charAt(1)
				checksumPair = [{ checksum, correctionValue}];
				webSocketTakePOS.send(CheckoutDialog06.formatMessage(CheckoutDialog06.createRecord10(checksumPair)));
			}

		<?php } else { ?>
			
        if (chr == "\n") {
            var weightOutput = settings.weightRegex.exec(buffer);
            var stableOutput = settings.stableRegex.test(buffer);

            if (weightOutput != null) {
                settings.onUpdate(weightOutput[1], stableOutput);
            } else {
				console.log("buffer: " + buffer);
			}
            buffer = '';
        } else {
            buffer = buffer + chr;
        }
		console.log("buffer: " + buffer);
		
		<?php } ?>
    };

    var onConnect = function () {
        settings.onConnect();
    };

    var onDisconnect = function () {
        settings.onDisconnect();
        reconnect();
    };

    var connect = function () {
        websocket = new WebSocket(settings.url);
        websocket.onopen = onConnect;
        websocket.onclose = onDisconnect;
        websocket.onmessage = onMessage;
    };

    var reconnect = function () {
        connect();
    };

    connect();
    
    return websocket;
}

var globalWeight = null;

var webSocketWeight = WebSocketWeigh({
	url: '<?php echo $conf->global->WEIGHINGSCALE_WEBSOCKET_URL; ?>',
    onUpdate: function (weight, stable) {
    	globalWeight = weight;
        console.log("onUpdate: " + weight + " is stable: " + stable);
    },
});

<?php if ($conf->global->WEIGHINGSCALE_PROTOCOL == "diag06") { ?>

/**
 * Etats de l'automate à états finis représentant l'utilisation du protocole diaglog-06 par le client.
 */
var ClientStates = {
	'WAITING_FOR_COMMAND' : "En attente d'une commande",
	'SENDING_UNITPRICE_BEFORE_WEIGHING' : "Envoi du prix unitaire",
	'ACK_RECEIVED_FOR_UNITPRICE' : "Acquittement reçu suite à l'envoi du prix unitaire",
	'REQUESTED_WEIGHING_SCALE' : "Pesée demandée",
	'CHECKSUM' : "Demande de somme de contrôle",
};

var currentWeight = 0;
var currentCallback = null;
var currentErrorCallback = null;

function askForWeight(unitPrice, callback, errorCallback) {
	currentStateClient = ClientStates.SENDING_UNITPRICE_BEFORE_WEIGHING;
	currentCallback = callback;
	currentErrorCallback = errorCallback;
	console.log(currentStateClient);
	sendUnitPrice(unitPrice);
}

function sendUnitPrice(price) {
	if (webSocketWeight !== undefined) {
		webSocketWeight.send(
			String.fromCharCode(0x04, 0x02, 0x30, 0x31, 0x1b) +
			CheckoutDialog06.fromFloatAsStringToDialog06(price) +
			String.fromCharCode(0x1b, 0x03)
		);
	}
}

function ENQ() {
	if (webSocketWeight !== undefined) {
		if (currentStateClient == ClientStates.ACK_RECEIVED_FOR_UNITPRICE) {
			currentStateClient = ClientStates.REQUESTED_WEIGHING_SCALE;
			console.log(currentStateClient);
		}
		webSocketWeight.send(CheckoutDialog06.formatMessage(CheckoutDialog06.createENQ()));
	}
}

<?php } ?>


// ===============================================
// WebSocketPrinter
// ===============================================

function WebSocketPrinter(options) {
	var defaults = {
		onConnect: function () {
		},
		onDisconnect: function () {
		},
		onUpdate: function () {
		},
	};

	var settings = Object.assign({}, defaults, options);
	var websocket;
	var connected = false;

	var onMessage = function (evt) {
		settings.onUpdate(evt.data);
	};

	var onConnect = function () {
		connected = true;
		settings.onConnect();
	};

	var onDisconnect = function () {
		connected = false;
		settings.onDisconnect();
		reconnect();
	};

	var connect = function () {
		websocket = new WebSocket(settings.url);
		websocket.onopen = onConnect;
		websocket.onclose = onDisconnect;
		websocket.onmessage = onMessage;
	};

	var reconnect = function () {
		connect();
	};

	this.submit = function (data) {
		if (Array.isArray(data)) {
			data.forEach(function (element) {
				websocket.send(JSON.stringify(element));
			});
		} else {
			websocket.send(JSON.stringify(data));
		}
	};

	this.isConnected = function () {
		return connected;
	};

	connect();
}

var url = window.location.pathname;
if (url.includes('/takepos/index.php') || url.includes('/compta/facture/card.php')) {

	var printService = new WebSocketPrinter({
		url: "<?php echo $ws;
		if ($conf->global->{'DIRECTPRINTWHB_IPADDRESS' . $terminaltouse}) {
			echo $conf->global->{'DIRECTPRINTWHB_IPADDRESS' . $terminaltouse};
		} else {
			echo "127.0.0.1";
		}
		echo ":";
		if ($conf->global->{'DIRECTPRINTWHB_PORT' . $terminaltouse}) {
			echo $conf->global->{'DIRECTPRINTWHB_PORT' . $terminaltouse};
		} else {
			echo "12212";
		}
		if ($conf->global->{'DIRECTPRINTWHB_PRINTER_SERVICE_NAME' . $terminaltouse}) {
			echo $conf->global->{'DIRECTPRINTWHB_PRINTER_SERVICE_NAME' . $terminaltouse};
		} else {
			echo "/print/INVOICE";
		} ?>",

		onConnect: function () {
			$.jnotify("<?php echo $langs->trans('Connected');?>",
				"info",
				{ timeout: 5 },
				{
					remove: function () { }
				});
			console.log('Connected');
		},
		onDisconnect: function () {
			$.jnotify("<?php echo $langs->trans('Disconnected');?>",
				"error",
				{ timeout: 5 },
				{
					remove: function () {}
				});
			console.log('Disconnected');
		},
		onUpdate: function (message) {
			// Do nothing
			//$.jnotify(message,
			//	"info",
			//	{timeout: 5},
			//	{
			//		remove: function () { }
			//	});

			//parent.jQuery.colorbox.close();
			//console.log(message);
		},
	});


	//TAKEPOS Action button
	if (url.includes('/takepos/index.php')) {
	
		idproduct = "";
		
		$(document).ready(function() {
			// Selectionne le noeud dont les mutations seront observées
			var targetNode = document.getElementById("poslines");
	
			// Options de l'observateur (quelles sont les mutations à observer)
			var config = { attributes: false, childList: true };
	
			// Fonction callback à éxécuter quand une mutation est observée
			var callback = function (mutationsList) {
				for (var mutation of mutationsList) {
					if (mutation.type == "childList") {
						console.log("Un noeud enfant a été ajouté ou supprimé.");
					} else if (mutation.type == "attributes") {
						console.log("L'attribut '" + mutation.attributeName + "' a été modifié.");
					}
					// Substitution des fonctions Javascript pour les boutons d'action
					var buttons = document.querySelectorAll(".actionbutton, .actionbuttondisabled");
					for (var button of buttons) {
						if (button["attributes"]["onclick"].value.includes("DolibarrTakeposPrinting") ||
							button["attributes"]["onclick"].value.includes("TakeposConnector") ||
							button["attributes"]["onclick"].value.includes("TakeposPrintingOrder") ||
							button["attributes"]["onclick"].value.includes("TakeposPrinting")) {
							button["attributes"]["onclick"].value = "DirectPrintWHBDolibarrTakeposPrinting(placeid);";
						}
						if (button["attributes"]["onclick"].value.includes("DolibarrOpenDrawer")) {
							button["attributes"]["onclick"].value = "DirectPrintWHBDolibarrOpenDrawer();";
						}
						<?php if ($conf->global->MAIN_MODULE_TAKEPOSASORDER) { ?>
						// Activer ou désactiver le bouton SAVE_AS_ORDER
						console.log("button event: " + button["attributes"]["onclick"].value);
						if (button["attributes"]["onclick"].value.includes("SaveAsCommand();") ||
							button["attributes"]["onclick"].value.includes("DirectPayment();")) {
							if ($("#tablelines")[0].tBodies[0].rows[1].childNodes[0].innerHTML.includes("<?php echo $langs->trans("Empty"); ?>")) {
								button.disabled = true;
								button.classList.remove("actionbutton");
								button.classList.add("actionbuttondisabled");
							} else {
								button.disabled = false;
								button.classList.remove("actionbuttondisabled");
								button.classList.add("actionbutton");
							}
						}
						<?php } ?>
					}
					// Substitution pour le bouton d'impression après paiement
					var buttonPrint = document.getElementById("buttonprint");
					if (buttonPrint != null && (
							buttonPrint["attributes"]["onclick"].value.includes("DolibarrTakeposPrinting") ||
							buttonPrint["attributes"]["onclick"].value.includes("TakeposConnector") ||
							buttonPrint["attributes"]["onclick"].value.includes("TakeposPrintingOrder") ||
							buttonPrint["attributes"]["onclick"].value.includes("TakeposPrinting"))) {
						buttonPrint["attributes"]["onclick"].value = "DirectPrintWHBDolibarrTakeposPrinting(placeid);";
						buttonPrint.setAttribute("class", "butAction");
						buttonPrint.nextElementSibling.setAttribute("class", "butAction");
					}
				}
			};
	
			// Créé une instance de l'observateur lié à la fonction de callback
			var observer = new MutationObserver(callback);
	
			// Commence à observer le noeud cible pour les mutations précédemment configurées
			observer.observe(targetNode, config);
		});
	
	}
	
	
	function DirectPrintWHBDolibarrTakeposPrinting(id) {
		console.log("DolibarrTakeposPrinting Printing invoice ticket " + id)
		$.ajax({
			type: "GET",
			data: {token: '<?php echo currentToken(); ?>'},
			url: "<?php print dol_buildpath('/takeposconnector', 2) . '/ajax/ajax.php?action=printinvoiceticket&term=' . urlencode($_SESSION["takeposterminal"]) . '&id='; ?>" + id,
			success: function (getdata) {
				printService.submit({
					"type": "<?php echo $conf->global->{'DIRECTPRINTWHB_TPPRINTERID' . $terminaltouse};?>",
					"raw_content": "\"" + getdata + "\""
				});
				console.log('Call /blockedlog/ajax/block-add on output of receipt.php.');
				$.post('<?php echo DOL_URL_ROOT; ?>/blockedlog/ajax/block-add.php', {
					id: id,
					element: 'facture',
					action: 'DOC_PREVIEW',
					token: '<?php echo currentToken(); ?>'
				   }
				);
			}
		});
	}

	function DirectPrintWHBDolibarrOpenDrawer() {
		console.log("DolibarrOpenDrawer call ajax url /ajax/ajax.php?action=opendrawer&term=<?php print urlencode($_SESSION["takeposterminal"]); ?>");
		$.ajax({
			type: "GET",
			data: {token: '<?php echo currentToken(); ?>'},
			url: "<?php print dol_buildpath('/directprintwhb', 2) . '/ajax/ajax.php?action=opendrawer&term=' . urlencode($_SESSION["takeposterminal"]); ?>",
			success: function (getdata) {
				printService.submit({
					"type": "<?php echo $conf->global->{'DIRECTPRINTWHB_TPPRINTERID' . $terminaltouse};?>",
					"raw_content": "\"" + getdata + "\""
				});
			}
		});
	}

}

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

$langs->load('takeposconnector@takeposconnector');

dol_include_once('/takeposconnector/lib/takeposconnector.lib.php');

$ws = 'ws://';
if (takeposconnectorGetConf('DIRECTPRINTWHB_SECURE', $terminaltouse) == 'oui') {
	$ws = 'wss://';
}

// Appareils configurés pour ce terminal — détermine les indicateurs d'état affichés dans le topnav.
$tpcScaleUrl   = takeposconnectorGetConf('WEIGHINGSCALE_WEBSOCKET_URL', $terminaltouse);
$tpcDisplayUrl = takeposconnectorGetConf('CUSTOMERDISPLAY_WEBSOCKET_URL', $terminaltouse);
$tpcHasScale   = !empty($tpcScaleUrl);
$tpcHasDisplay = !empty($tpcDisplayUrl);
$tpcHasDrawer  = (getDolGlobalInt('TAKEPOS_ADD_BUTTON_OPEN_DRAWER'.$terminaltouse) > 0);

?>

// ===============================================
// Indicateurs d'état & reconnexion robuste (TPC)
// Aligné sur la console de simulation du webapp-hardware-bridge :
// reconnexion temporisée, anti-empilement, et état exposé à l'UI.
// ===============================================

// Délai de base avant tentative de reconnexion automatique (ms). Le délai réel croît
// exponentiellement à chaque échec consécutif (back-off) jusqu'à un plafond, pour qu'un
// appareil durablement absent ne sature pas la file d'admission WebSocket de Firefox
// (un seul handshake en cours par hôte) et ne bloque pas la (re)connexion des autres.
var TPC_DELAI_RECONNEXION = 1000;
var TPC_DELAI_RECONNEXION_MAX = 30000;

// Dernier état connu par appareil ('connexion' | 'ouvert' | 'ferme'), pour
// pouvoir réafficher les pastilles dès qu'elles sont injectées dans le DOM
// (les sockets se connectent avant l'injection des icônes).
var tpcEtats = {};

// Timers de reconnexion en attente, indexés par clé d'appareil, pour ne jamais
// lancer deux chaînes de reconnexion en parallèle sur un même socket.
var tpcTimersReconnexion = {};

// Nombre d'échecs consécutifs par appareil, pour calculer le back-off. Remis à zéro
// dès qu'une connexion aboutit (voir tpcMajEtat) ou sur reconnexion manuelle.
var tpcTentatives = {};

// Références des wrappers WebSocket, pour la reconnexion forcée au clic.
var tpcAppareils = {};

// Libellés lisibles des appareils.
var tpcLibelles = {
	scale:   <?php echo json_encode($langs->transnoentitiesnoconv('TPCDeviceScale')); ?>,
	display: <?php echo json_encode($langs->transnoentitiesnoconv('TPCDeviceDisplay')); ?>,
	printer: <?php echo json_encode($langs->transnoentitiesnoconv('TPCDevicePrinter')); ?>,
	drawer:  <?php echo json_encode($langs->transnoentitiesnoconv('TPCDeviceDrawer')); ?>
};

function tpcTexteEtat(etat) {
	return {
		'connexion': <?php echo json_encode($langs->transnoentitiesnoconv('TPCStateConnecting')); ?>,
		'ouvert':    <?php echo json_encode($langs->transnoentitiesnoconv('TPCStateConnected')); ?>,
		'ferme':     <?php echo json_encode($langs->transnoentitiesnoconv('TPCStateDisconnected')); ?>
	}[etat] || etat;
}

// Applique l'état (couleur + infobulle) à la pastille d'un appareil, si présente.
function tpcAppliquerEtat(cle, etat) {
	var dot = document.getElementById('tpc-dot-' + cle);
	if (!dot) {
		return;
	}
	dot.className = 'tpc-dot tpc-dot-' + etat;
	if (dot.parentNode) {
		dot.parentNode.title = (tpcLibelles[cle] || cle) + ' — ' + tpcTexteEtat(etat);
	}
}

// Met à jour l'état d'un appareil. Le tiroir-caisse n'a pas de socket propre :
// il passe par l'imprimante, donc son état recopie celui de 'printer'.
function tpcMajEtat(cle, etat) {
	tpcEtats[cle] = etat;
	if (etat === 'ouvert') {
		tpcTentatives[cle] = 0;
	}
	tpcAppliquerEtat(cle, etat);
	if (cle === 'printer') {
		tpcEtats['drawer'] = etat;
		tpcAppliquerEtat('drawer', etat);
	}
}

// Reprogramme une reconnexion après coupure, sans empiler les chaînes parallèles
// (corrige la double reconnexion onerror+onclose de l'ancienne balance).
function tpcReconnecterAvecDelai(cle, connectFn) {
	if (tpcTimersReconnexion[cle]) {
		return;
	}
	var n = tpcTentatives[cle] || 0;
	tpcTentatives[cle] = n + 1;
	var delai = Math.min(TPC_DELAI_RECONNEXION * Math.pow(2, n), TPC_DELAI_RECONNEXION_MAX);
	console.log('[TPC] reconnexion ' + cle + ' #' + (n + 1) + ' programmée dans ' + delai + ' ms');
	tpcTimersReconnexion[cle] = setTimeout(function () {
		tpcTimersReconnexion[cle] = null;
		connectFn();
	}, delai);
}

// Ferme un socket sans déclencher la reconnexion auto (neutralise les handlers).
function tpcFermerProprement(ws) {
	if (ws !== undefined && ws !== null) {
		ws.onclose = null;
		ws.onerror = null;
		try { ws.close(); } catch (e) {}
	}
}

// Reconnexion immédiate déclenchée par l'utilisateur (clic sur la pastille).
function tpcForcerReconnexion(cle) {
	if (cle === 'drawer') {
		cle = 'printer';
	}
	if (tpcTimersReconnexion[cle]) {
		clearTimeout(tpcTimersReconnexion[cle]);
		tpcTimersReconnexion[cle] = null;
	}
	// Clic utilisateur = on repart d'un back-off neuf (reconnexion immédiate).
	tpcTentatives[cle] = 0;
	var appareil = tpcAppareils[cle];
	if (appareil && typeof appareil.reconnecterMaintenant === 'function') {
		appareil.reconnecterMaintenant();
	}
}

// Ferme proprement tous les sockets matériel avant que la page ne soit déchargée
// (rechargement TakePOS, changement de terminal, fermeture d'onglet). Sans ça, le
// WHB ne détecte les connexions mortes qu'au timeout de ping (~5 s, voir Server.java),
// laissant des abonnés fantômes le temps que la nouvelle page se reconnecte.
function tpcFermerTousLesAppareils() {
	// Annule toute reconnexion programmée pour ne pas relancer un socket pendant l'unload.
	for (var cle in tpcTimersReconnexion) {
		if (tpcTimersReconnexion[cle]) {
			clearTimeout(tpcTimersReconnexion[cle]);
			tpcTimersReconnexion[cle] = null;
		}
	}
	for (var cle2 in tpcAppareils) {
		var appareil = tpcAppareils[cle2];
		if (appareil && typeof appareil.fermer === 'function') {
			appareil.fermer();
		}
	}
}

// 'pagehide' couvre rechargement, navigation et fermeture d'onglet (plus fiable que
// 'beforeunload'). Un socket ouvert rend de toute façon la page inéligible au bfcache,
// donc aucun risque de fermer les sockets d'une page qui serait restaurée.
window.addEventListener('pagehide', tpcFermerTousLesAppareils);

// ===============================================
// WebSocketSerial (defaults to CustomerDisplay)
// ===============================================

function WebSocketSerial(options) {
    var defaults = {
        url: 'ws://localhost:12212/serial/DISPLAY',
        cle: 'display',
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

    var userOnOpen = null;

    var onConnect = function () {
        tpcMajEtat(settings.cle, 'ouvert');
        settings.onConnect();
        if (typeof userOnOpen === 'function') {
            userOnOpen();
        }
    };

    var onDisconnect = function (evt) {
        tpcMajEtat(settings.cle, 'ferme');
        settings.onDisconnect();
        // Fermeture propre par le WHB car le périphérique est absent (code 4001) : on sonde
        // à cadence de base sans escalade du back-off, pour repasser au vert ~1 s après le
        // rebranchage. Les fermetures anormales (WHB injoignable) gardent le back-off.
        if (evt && evt.code === 4001) {
            tpcTentatives[settings.cle] = 0;
        }
        tpcReconnecterAvecDelai(settings.cle, connect);
    };

    var connect = function () {
        tpcMajEtat(settings.cle, 'connexion');
        websocket = new WebSocket(settings.url);
        websocket.onopen = onConnect;
        websocket.onclose = onDisconnect;
        websocket.onmessage = onMessage;
        websocket.onerror = function (evt) { console.log('WebSocket error (' + settings.cle + '): ', evt); };
    };

	this.readyState = function () {
		return websocket ? websocket.readyState : WebSocket.CLOSED;
	};

	// Enregistre un callback persistant rejoué à chaque (re)connexion, y compris
	// après une reconnexion automatique (l'ancien onOpen écrasait websocket.onopen
	// du socket courant et était perdu au reconnect).
	this.onOpen = function(callback) {
		userOnOpen = callback;
		if (websocket && websocket.readyState === WebSocket.OPEN) {
			callback();
		}
	};

    this.send = function (message) {
        websocket.send(message);
    };

    this.reconnecterMaintenant = function () {
        tpcFermerProprement(websocket);
        connect();
    };

    // Ferme le socket sans relance auto (déchargement de page).
    this.fermer = function () {
        tpcFermerProprement(websocket);
    };

    connect();
}

// Make it available — uniquement si un afficheur est configuré pour ce terminal.
// Instancier une socket vers une URL vide/absente faisait échouer son handshake en
// boucle et, via la sérialisation d'admission WebSocket de Firefox (un seul handshake
// en cours par hôte), bloquait ~60 s la (re)connexion des autres appareils au rechargement.
<?php if ($tpcHasDisplay) { ?>
const webSocketCustomerDisplay = new WebSocketSerial({
	cle: 'display',
	url: '<?php echo takeposconnectorGetConf('CUSTOMERDISPLAY_WEBSOCKET_URL', $terminaltouse); ?>'
});
tpcAppareils['display'] = webSocketCustomerDisplay;
<?php } ?>
	
// ===============================================
// WebSocketWeigh (default protocol)
// ===============================================

function WebSocketWeigh(options) {
    var defaults = {
        url: 'ws://localhost:12212/serial/WEIGH',
        cle: 'scale',
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
    
    var onError = function(evt) {
    	// Ne PAS reconnecter ici : un onerror est toujours suivi d'un onclose qui
    	// se charge de la reconnexion. Reconnecter aux deux endroits empilait les
    	// sockets (croissance exponentielle d'instances orphelines) à chaque coupure.
    	console.log("Error (scale): ", evt);
    }

    var onMessage = function (evt) {
        var chr = evt.data;
		console.log("data: " + chr);

		<?php if (takeposconnectorGetConf('WEIGHINGSCALE_PROTOCOL', $terminaltouse) == "diag06") { ?>

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
        tpcMajEtat(settings.cle, 'ouvert');
        settings.onConnect();
    };

    var onDisconnect = function (evt) {
        tpcMajEtat(settings.cle, 'ferme');
        settings.onDisconnect();
        // Fermeture propre par le WHB car le périphérique est absent (code 4001) : on sonde
        // à cadence de base sans escalade du back-off, pour repasser au vert ~1 s après le
        // rebranchage. Les fermetures anormales (WHB injoignable) gardent le back-off.
        if (evt && evt.code === 4001) {
            tpcTentatives[settings.cle] = 0;
        }
        tpcReconnecterAvecDelai(settings.cle, connect);
    };

    var connect = function () {
        tpcMajEtat(settings.cle, 'connexion');
        websocket = new WebSocket(settings.url);
        websocket.onopen = onConnect;
        websocket.onclose = onDisconnect;
        websocket.onmessage = onMessage;
        websocket.onerror = onError;
    };

    // On expose le wrapper (et non le socket natif) pour que la référence
    // `webSocketWeight` reste valable après une reconnexion : auparavant elle
    // pointait sur le socket mort après la première coupure et la pesée ne
    // repartait plus (readyState restait CLOSED).
    this.readyState = function () {
        return websocket ? websocket.readyState : WebSocket.CLOSED;
    };

    this.send = function (message) {
        websocket.send(message);
    };

    this.reconnecterMaintenant = function () {
        tpcFermerProprement(websocket);
        connect();
    };

    // Ferme le socket sans relance auto (déchargement de page).
    this.fermer = function () {
        tpcFermerProprement(websocket);
    };

    connect();
}

var globalWeight = null;

// Déclarée même sans balance configurée : le code diag06 plus bas la teste via
// `webSocketWeight !== undefined`. On ne l'instancie que si une balance est configurée
// pour ce terminal, pour ne pas polluer la file d'admission WebSocket de Firefox.
var webSocketWeight;
<?php if ($tpcHasScale) { ?>
webSocketWeight = new WebSocketWeigh({
	cle: 'scale',
	url: '<?php echo takeposconnectorGetConf('WEIGHINGSCALE_WEBSOCKET_URL', $terminaltouse); ?>',
    onUpdate: function (weight, stable) {
    	globalWeight = weight;
        console.log("onUpdate: " + weight + " is stable: " + stable);
    },
});
tpcAppareils['scale'] = webSocketWeight;
<?php } ?>

<?php if (takeposconnectorGetConf('WEIGHINGSCALE_PROTOCOL', $terminaltouse) == "diag06") { ?>

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
	startWeighingSequence(unitPrice);
}

function startWeighingSequence(unitPrice) {
	if (webSocketWeight !== undefined && webSocketWeight.readyState() === WebSocket.OPEN) {
		sendUnitPrice(unitPrice);
	} else {
		currentErrorCallback("Cannot send unitPrice to scale");
		currentCallback();
	}
}

function sendUnitPrice(price) {
	webSocketWeight.send(
		String.fromCharCode(0x04, 0x02, 0x30, 0x31, 0x1b) +
		CheckoutDialog06.fromPriceAsStringToDialog06(price) +
		String.fromCharCode(0x1b, 0x03)
	);
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
		cle: 'printer',
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
		tpcMajEtat(settings.cle, 'ouvert');
		settings.onConnect();
	};

	var onDisconnect = function (evt) {
		connected = false;
		tpcMajEtat(settings.cle, 'ferme');
		settings.onDisconnect();
		// Cf. WebSocketSerial : fermeture 4001 (périphérique absent) -> sondage à cadence
		// de base sans escalade (utile si l'imprimante passe par un canal série).
		if (evt && evt.code === 4001) {
			tpcTentatives[settings.cle] = 0;
		}
		tpcReconnecterAvecDelai(settings.cle, connect);
	};

	var connect = function () {
		tpcMajEtat(settings.cle, 'connexion');
		websocket = new WebSocket(settings.url);
		websocket.onopen = onConnect;
		websocket.onclose = onDisconnect;
		websocket.onmessage = onMessage;
		websocket.onerror = function (evt) { console.log('WebSocket error (' + settings.cle + '): ', evt); };
	};

	this.reconnecterMaintenant = function () {
		tpcFermerProprement(websocket);
		connect();
	};

	// Ferme le socket sans relance auto (déchargement de page).
	this.fermer = function () {
		tpcFermerProprement(websocket);
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
	
	this.submitRaw = function (b64) {                                                                                                                                  
		var bin = atob(b64);                                                                                                                                             
 		var bytes = new Uint8Array(bin.length);                                                                                                                          
 		for (var i = 0; i < bin.length; i++) {                                                                                                                           
 			bytes[i] = bin.charCodeAt(i);                                                                                                                                  
		}                                                                                                                                                                
		websocket.send(bytes);                                                                                                                                           
	};

	this.isConnected = function () {
		return connected;
	};

	connect();
}

// Guards against this file's device-connection/topnav-injection logic running more than once
// on the same page (e.g. if addHtmlHeader ever emits the <script> tag twice): without it, a
// second run opens a second WebSocket to the printer (leaked, never closed) and duplicates the
// topnav hardware icons.
if (!window.tpcTakeposconnectorLoaded) {
window.tpcTakeposconnectorLoaded = true;

var url = window.location.pathname;
if (url.includes('/takepos/index.php') || url.includes('/compta/facture/card.php')) {

	var printService = new WebSocketPrinter({
		cle: 'printer',
		url: "<?php echo $ws;
		$ipaddress = takeposconnectorGetConf('DIRECTPRINTWHB_IPADDRESS', $terminaltouse);
		echo $ipaddress ? $ipaddress : "127.0.0.1";
		echo ":";
		$port = takeposconnectorGetConf('DIRECTPRINTWHB_PORT', $terminaltouse);
		echo $port ? $port : "12212";
		$servicename = takeposconnectorGetConf('DIRECTPRINTWHB_PRINTER_SERVICE_NAME', $terminaltouse);
		echo $servicename ? $servicename : "/print/INVOICE"; ?>",

		onConnect: function () {
			// L'état est désormais visible via la pastille du topnav (tpcMajEtat).
			console.log('Connected');
		},
		onDisconnect: function () {
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
	tpcAppareils['printer'] = printService;


	//TAKEPOS Action button
	if (url.includes('/takepos/index.php')) {

		idproduct = "";

		// --- Indicateurs d'état des connexions matériel dans le topnav ---
		$(document).ready(function () {
			var $hote = $('#topnav-left');
			if (!$hote.length) {
				return;
			}

			function tpcIcone(cle, classeFa) {
				return '<span class="tpc-hw" data-cle="' + cle + '" title="' + (tpcLibelles[cle] || cle) + '">' +
					'<span class="' + classeFa + '"></span>' +
					'<span class="tpc-dot tpc-dot-ferme" id="tpc-dot-' + cle + '"></span>' +
					'</span>';
			}

			// On n'affiche que les appareils réellement configurés sur ce terminal.
			var html = '<div class="inline-block valignmiddle tpc-hwstatus">';
			<?php if ($tpcHasScale) { ?>
			html += tpcIcone('scale', 'fa fa-balance-scale');
			<?php } ?>
			<?php if ($tpcHasDisplay) { ?>
			html += tpcIcone('display', 'fa fa-desktop');
			<?php } ?>
			html += tpcIcone('printer', 'fa fa-print');
			<?php if ($tpcHasDrawer) { ?>
			html += tpcIcone('drawer', 'fa fa-cash-register');
			<?php } ?>
			html += '</div>';

			// Inséré juste après le bloc entrepôt s'il existe, sinon en fin de topnav-left.
			var $apres = $('#infowarehouse');
			if ($apres.length) {
				$apres.after(html);
			} else {
				$hote.append(html);
			}

			// Réapplique les états déjà connus (les sockets se connectent avant l'injection).
			for (var cle in tpcEtats) {
				tpcAppliquerEtat(cle, tpcEtats[cle]);
			}

			// Clic sur une pastille = reconnexion immédiate de l'appareil.
			$('.tpc-hwstatus .tpc-hw').on('click', function () {
				tpcForcerReconnexion($(this).data('cle'));
			});
		});

		$(document).ready(function() {
			// Selectionne le noeud dont les mutations seront observées
			var targetNode = document.getElementById("poslines");
	
			// Options de l'observateur (quelles sont les mutations à observer)
			var config = { attributes: false, childList: true };
	
			// Fonction callback à éxécuter quand une mutation est observée
			var callback = function (mutationsList) {
				for (var mutation of mutationsList) {
					/*
					if (mutation.type == "childList") {
						console.log("Un noeud enfant a été ajouté ou supprimé.");
					} else if (mutation.type == "attributes") {
						console.log("L'attribut '" + mutation.attributeName + "' a été modifié.");
					}
					*/
					// Substitution des fonctions Javascript pour les boutons d'action
					var buttons = document.querySelectorAll(".actionbutton, .actionbuttondisabled");
					for (var button of buttons) {
						if (button["attributes"]["onclick"].value.includes("Print") ||
							button["attributes"]["onclick"].value.includes("DolibarrTakeposPrinting") ||
							button["attributes"]["onclick"].value.includes("TakeposConnector") ||
							button["attributes"]["onclick"].value.includes("TakeposPrintingOrder") ||
							button["attributes"]["onclick"].value.includes("TakeposPrinting") ||
							button["attributes"]["onclick"].value.includes("PrintByESCPOSOld") ||
							button["attributes"]["onclick"].value.includes("PrintByBrowser")) {
							button["attributes"]["onclick"].value = "DirectPrintWHBDolibarrTakeposPrinting(placeid);";
						}
						if (button["attributes"]["onclick"].value.includes("DolibarrOpenDrawer")) {
							button["attributes"]["onclick"].value = "DirectPrintWHBDolibarrOpenDrawer();";
						}
						<?php if ($conf->global->MAIN_MODULE_TAKEPOSASORDER) { ?>
						// Activer ou désactiver le bouton SAVE_AS_ORDER
						//console.log("button event: " + button["attributes"]["onclick"].value);
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
							buttonPrint["attributes"]["onclick"].value.includes("Print") ||
							buttonPrint["attributes"]["onclick"].value.includes("DolibarrTakeposPrinting") ||
							buttonPrint["attributes"]["onclick"].value.includes("TakeposConnector") ||
							buttonPrint["attributes"]["onclick"].value.includes("TakeposPrintingOrder") ||
							buttonPrint["attributes"]["onclick"].value.includes("TakeposPrinting") ||
							buttonPrint["attributes"]["onclick"].value.includes("PrintByESCPOSOld") ||
							buttonPrint["attributes"]["onclick"].value.includes("PrintByBrowser"))) {
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
} // window.tpcTakeposconnectorLoaded guard


	function DirectPrintWHBDolibarrTakeposPrinting(id) {
		console.log("DolibarrTakeposPrinting Printing invoice ticket " + id)
		$.ajax({
			type: "GET",
			data: {token: '<?php echo currentToken(); ?>'},
			url: "<?php print dol_buildpath('/takeposconnector', 2) . '/ajax/ajax.php?action=printinvoiceticket&term=' . urlencode($_SESSION["takeposterminal"]) . '&id='; ?>" + id,
			success: function (getdata) {
				<?php  if ("TEST" == takeposconnectorGetConf('DIRECTPRINTWHB_TPPRINTERID', $terminaltouse)) { ?>
				printService.submit(
				{
					"type": "<?php echo takeposconnectorGetConf('DIRECTPRINTWHB_TPPRINTERID', $terminaltouse);?>",
					"raw_content": "\"" + getdata + "\""
				});
				<?php } else { ?>
				printService.submitRaw(getdata);
				<?php } ?>
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
					"type": "<?php echo takeposconnectorGetConf('DIRECTPRINTWHB_TPPRINTERID', $terminaltouse);?>",
					"raw_content": "\"" + getdata + "\""
				});
			}
		});
	}

}

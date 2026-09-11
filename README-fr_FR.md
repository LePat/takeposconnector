# TAKEPOSCONNECTOR pour [DOLIBARR ERP CRM](https://www.dolibarr.org)

## Fonctionnalités

La fonction principale du module **TakePOSConnector** est de permettre à **TakePOS** de piloter
le matériel de caisse suivant :
- balance
- imprimante thermique
- afficheur client
- tiroir-caisse

Il prend en charge plusieurs transports/protocoles pour dialoguer avec ce matériel :
- **Protocole Dialog-06**, pour les balances qui nécessitent un échange requête/réponse
  explicite (poids + prix unitaire) plutôt qu'un flux de poids continu.
- **Transmission continue du poids** (protocoles de balance plus anciens/plus simples).
- **Imprimantes thermiques ESC/POS** via le Webapp-Hardware-Bridge (WHB), y compris en sortie
  binaire brute pour les tickets.

Autres fonctionnalités :
- **Configuration par terminal avec héritage** : chaque paramètre (URL WebSocket de la balance
  et de l'afficheur, connexion imprimante, largeur du ticket, etc.) possède une **valeur
  commune** partagée par tous les terminaux, et peut optionnellement être **surchargé par
  terminal** depuis une page de configuration à onglets — inutile de répéter les mêmes réglages
  sur chaque caisse.
- **Indicateurs d'état des connexions matériel** dans la barre supérieure de TakePOS (balance,
  afficheur client, imprimante, tiroir-caisse), avec reconnexion WebSocket automatique.
  ![Panneau d'état](img/state_panel.png "États des connexions")
- **Largeur de ticket configurable** (nombre de caractères par ligne), par terminal, pour
  s'adapter aux imprimantes thermiques étroites (ex. 42 colonnes) plutôt que la largeur par
  défaut de 48 colonnes.

## Compatibilité

**Dolibarr >= 24.0.1 est requis pour la balance et l'afficheur client via le
Webapp-Hardware-Bridge (WHB).** Le core n'a introduit le routage WHB pour ces deux
équipements qu'en version 24.0.1 (`TAKEPOS_CONNECTOR_TO_WHB_SCALE` /
`TAKEPOS_CONNECTOR_TO_WHB_CUSTOMER_DISPLAY`). Sur toute version antérieure, TakePOS ne dispose
d'aucun mécanisme de ce type dans le core — la balance et l'afficheur client font toujours un
appel HTTP direct vers `TAKEPOS_PRINT_SERVER`, quelle que soit la configuration de ce module,
sans possibilité d'activer la voie WHB/WebSocket.

L'imprimante thermique et le tiroir-caisse via WHB ne dépendent pas de ce routage et
fonctionnent aussi sur des versions antérieures de Dolibarr.

## Prérequis

- le **New TakePOS Connector PHP** pour utiliser le protocole balance "$", l'imprimante
  thermique, le tiroir-caisse et l'afficheur client
- le **Webapp-Hardware-Bridge** pour utiliser la transmission continue du poids ou le protocole
  Dialog-06 pour la balance, l'imprimante thermique ESC-POS, l'afficheur client et le
  tiroir-caisse

## Configuration du module

#### Configuration générale

- Installer le module takeposconnector depuis : <https://github.com/LePat/takeposconnector>
- Activer le module takeposconnector.
- Ajouter le paramètre `TAKEPOS_PRINT_METHOD`, valeur = `takeposconnector`
- Définir le serveur d'impression en ajoutant le paramètre `TAKEPOS_PRINT_SERVER`, valeur =
  `http://localhost:12212`
- Activer l'afficheur client en ajoutant le paramètre `TAKEPOS_CUSTOMER_DISPLAY`, valeur = `1`
- Activer la balance en ajoutant le paramètre `TAKEPOS_WEIGHING_SCALE`, valeur = `1`

#### Utilisation du New TakePOS Connector PHP

Il écoute les requêtes HTTP sur le port 12212.

Installer le New TakePOS Connector PHP depuis :
<https://github.com/andreubisquerra/TakePOS-connector-PHP>

Il doit fonctionner avec la configuration générale ci-dessus, le module takeposconnector
utilisant par défaut le New TakePOS Connector PHP :
- `/print/index.php` est ajouté à l'URL (`TAKEPOS_PRINT_SERVER`) pour se connecter à
  l'imprimante
- `/print/drawer.php` est ajouté à l'URL (`TAKEPOS_PRINT_SERVER`) pour se connecter au
  tiroir-caisse
- `/display/index.php` est ajouté à l'URL (`TAKEPOS_PRINT_SERVER`) pour se connecter à
  l'afficheur client
- `/scale/index.php` est ajouté à l'URL (`TAKEPOS_PRINT_SERVER`) pour se connecter à la balance

#### Utilisation du Webapp-Hardware-Bridge

Il ouvre des WebSockets sur le port 12212.

Installer le Webapp-Hardware-Bridge (WHB) depuis :
<https://github.com/LePat/webapp-hardware-bridge>

Configurer takeposconnector pour que TakePOS utilise le WHB :
- Webservice de la balance : `ws://127.0.0.1:12212/serial/WEIGH` (ou
  `ws://127.0.0.1:12212/takepos` pour des tests)
- Webservice de l'afficheur client : `ws://127.0.0.1:12212/serial/DISPLAY` (pour le tester dans
  la console WHB, lancer la commande `socat` et la configurer dans l'interface graphique du
  WHB)
- Imprimante thermique, pour chaque terminal défini dans TakePOS :
  - utilisation du SSL pour les WebSockets
  - nom d'hôte (`DIRECTPRINTWHB_IPADDRESS`) : `127.0.0.1`
  - port TCP (`DIRECTPRINTWHB_PORT`) : `12212`
  - nom du webservice (`DIRECTPRINTWHB_TPPRINTERID`) : `/print/INVOICE` (ou `/posprinter` pour
    des tests dans la console WHB)

#### Utilisation du TakePOS Connector Java

Il écoute les requêtes HTTP sur le port 8111.

Installer le TakePOS Connector Java depuis :
<https://github.com/andreubisquerra/TakePOS-Connector-Java>

Dans ce cas, changer la valeur du paramètre `TAKEPOS_PRINT_SERVER` pour `localhost` ou
`127.0.0.1` ; l'URL est filtrée avec `FILTER_VALIDATE_URL` pour déterminer si elle est complète
ou non. Si elle ne l'est pas, elle sera complétée ainsi :
`http://<TAKEPOS_PRINT_SERVER>:8111/print`. Seule l'imprimante est gérée dans ce cas.

#### Capture d'écran de la configuration du module TakePOSConnector

![Capture d'écran takeposconnector](img/setup.png "TakeposConnector")

> Note : cette capture d'écran est antérieure à la page de configuration à onglets par
> terminal et doit être mise à jour.

## Crédits

Ce module est un fork du module Dolibarr d'origine
[TakePOS-Connector](https://github.com/andreubisquerra/TakePOS-Connector) d'Andreu Bisquerra,
désormais maintenu de façon indépendante. Les mentions de copyright d'origine sont conservées
dans les fichiers sources concernés.

## Divers

TakePOS Connector (celui-ci), TakePOS Connector Java et New TakePOS Connector PHP ont des
usages différents.

D'autres modules externes sont disponibles sur [Dolistore.com](https://www.dolistore.com).

## Traductions

Les traductions peuvent être complétées manuellement en éditant les fichiers dans les
répertoires *langs*.

Ce module contient également un exemple de configuration pour Transifex, dans le répertoire
caché [.tx](.tx), ce qui permet de gérer les traductions via ce service.

Pour plus d'informations, voir la
[documentation du traducteur](https://wiki.dolibarr.org/index.php/Translator_documentation).

## Installation

### Depuis le fichier ZIP et l'interface graphique

- Si vous disposez du module sous forme de fichier zip (par exemple téléchargé depuis la
  marketplace [Dolistore](https://www.dolistore.com)), allez dans le menu
  `Accueil - Configuration - Modules - Déployer un module externe` et téléversez le fichier
  zip.

Note : si cet écran indique qu'il n'y a pas de répertoire `custom`, vérifiez votre
configuration :

- Dans votre répertoire d'installation Dolibarr, éditez le fichier `htdocs/conf/conf.php` et
  vérifiez que les lignes suivantes ne sont pas commentées :

    ```php
    //$dolibarr_main_url_root_alt ...
    //$dolibarr_main_document_root_alt ...
    ```

- Décommentez-les si nécessaire (supprimez le `//` en début de ligne) et affectez-leur une
  valeur cohérente avec votre installation Dolibarr

    Par exemple :

    - UNIX :
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = '/var/www/Dolibarr/htdocs/custom';
        ```

    - Windows :
        ```php
        $dolibarr_main_url_root_alt = '/custom';
        $dolibarr_main_document_root_alt = 'C:/My Web Sites/Dolibarr/htdocs/custom';
        ```

### Depuis un dépôt GIT

- Clonez le dépôt dans `$dolibarr_main_document_root_alt/takeposconnector`

```sh
cd ....../custom
git clone git@github.com:gitlogin/takeposconnector.git takeposconnector
```

### <a name="final_steps"></a>Étapes finales

Depuis votre navigateur :

  - Connectez-vous à Dolibarr en tant que super-administrateur
  - Allez dans "Configuration" -> "Modules"
  - Vous devriez maintenant pouvoir trouver et activer le module


## Licences

### Code principal

GPLv3 ou (à votre choix) toute version ultérieure. Voir le fichier COPYING pour plus
d'informations.

### Documentation

Tous les textes et readmes sont sous licence GFDL.

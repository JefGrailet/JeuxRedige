# Comment installer une copie de JeuxRédige

_(English version below)_

## Etape 1: installation de la base de données
Sur votre serveur, créez une nouvelle base données avec le nom de votre choix, en choisissant 
l'interclassement utf8_unicode_520_ci, puis importez le fichier `sample_db.sql` dans celle-ci. 
Cette base de données contient bien sûr toutes les tables SQL qui sont utilisées sur JeuxRédige 
mais aussi quelques contenus de base, comme une première liste de jeux et un premier compte 
d'utilisateur.

## Etape 2: paramétrage du site
Vous devez à présent modifier le fichier `src/config/Config.inc.php` si nécessaire. Ce fichier 
sert à renseigner une seule fois les informations de connexion à la base de données de votre 
serveur. Par défaut, le code fourni est configuré pour un serveur local (`localhost`) ayant une 
base de données `sample_db` avec le login par défaut `root` (sans mot de passe).

Notez la présence de trois paramètres supplémentaires à considérer si nécessaire:

* **_paths\_js\_extension_** renseigne l'extension par défaut de vos fichiers JavaScript. Si vous 
  souhaitez utiliser les scripts tels quels, vous n'avez rien à faire. Si en revanche vous 
  souhaitez par exemple les minimiser, faites-le en remplaçant l'extension `.js` par `.min.js`, 
  afin de pouvoir garder les scripts originaux, et modifiez _paths_js_extension_ en conséquence.
* **_protocol_** renseigne le protocole que vous utilisez pour accéder au site, typiquement HTTP 
  ou HTTPS. Ce paramètre doit être modifié (par exemple, "_https_" au lieu de "_http_") si vous 
  comptez utiliser un autre protocole et ce afin de correctement préfixer les URLs absolues vers 
  les images/clips vidéo stockés sur le site ou vers les articles.
* **_www\_prefix_** est un booléen indiquant si vos URLs doivent toujours contenir "www." devant 
  le nom de domaine afin d'uniformiser celles-ci. Par défaut, ce booléen est laissé à `false` en 
  vue d'utiliser le code sur un serveur local (`http://localhost/`).

**Remarque importante:** le fichier `src/libraries/Header.lib.php` se sert de 
`$_SERVER['DOCUMENT_ROOT']` et `$_SERVER['SERVER_NAME']` pour déterminer automatiquement le chemin 
absolu vers les fichiers du site et l'URL de base. Si votre serveur ne fournit pas de valeurs 
correctes pour ces variables, pensez à éditer les premières lignes de ce fichier.

## Etape 3: copie des fichiers sources
Copiez l'intégralité du contenu du dossier `src` (après les modifications de l'étape 2) dans votre 
dossier `www` (ou équivalent). Le contenu copié reprend également les sous-dossiers d'upload et les 
images qui correspondent au contenu fourni par défaut.

## Etape 4: premier accès
Vous pouvez à présent utiliser votre copie de JeuxRédige comme bon vous semble. Pour vous 
simplifier la vie, un premier compte utilisateur est disponible dans la base de données initiale. 
Les identifiants de ce compte sont donnés en italique ci-contre (les espaces sont à inclure):

* **Pseudonyme:** _AlainTouring_
* **Mot de passe:** _il n'y en a pas_

# How to install a clone of JeuxRédige

## Step 1: setting up the database
On your server, create a new database with a name of your choice, choosing utf8_unicode_520_ci as 
encoding, then import the `sample_db.sql` file inside it. This database contains of course all the 
SQL tables which are used by JeuxRédige but also provides sauf default content such as a first 
list of games and a first user account.

## Step 2: configuration of the website
You now have to edit the file `src/config/Config.inc.php` if necessary. This file is meant to 
provide only once the details to connect to your database. By default, the provided code is 
configured for a local server (`localhost`) having a `sample_db` database with the default login 
`root` (without password).

Note that there are three additional parameters you might want to edit too in some cases:

* **_paths\_js\_extension_** provides the default extension of your JavaScript files. If you wish 
  to use them "_as is_", you don't have to do anything. If, on the contrary, you wish (for 
  instance) to minimize them, do so while replacing the usual `.js` extension with `.min.js` in 
  order to keep the original scripts. Then, modify _paths_js_extension_ accordingly.
* **_protocol_** provides the protocol you use to access the website, e.g., HTTP or HTTPS. This 
  parameter must be updated (e.g., "_https_" instead of "_http_") if you intend to use another 
  protocol. This is necessary in order to correctly prefix all absolute URLs towards pictures or 
  video clips stored on the website or towards articles.
* **_www\_prefix_** is a boolean telling whether or not your URLs should always include the "www." 
  prefix before the domain name in order to standardize them. By default, this boolean is set to 
  `false`, which corresponds to the use case of deploying the code on a local server 
  (`http://localhost/`).

**Important remark:** the file `src/libraries/Header.lib.php` relies on 
`$_SERVER['DOCUMENT_ROOT']` and `$_SERVER['SERVER_NAME']` to find automatically the absolute path 
towards the files of the site as well as the base URL. If your server doesn't provide correct 
values for these variables, you might want to edit the first lines of this file.

## Step 3: copy of the source files
Copy the entirety of the `src` folder (after the modifications of step 2) in your `www` folder (or 
equivalent). The copied content also include the upload subfolders and the pictures that are part 
of the default content.

## Step 4: First use
You can now use your JeuxRédige copy as you like. To simplify your life, a first user account 
is available in the initial database. The credentials for this account are hereby given in italic 
(blank spaces must be included):

* **Pseudonym:** _AlainTouring_
* **Password:** _il n'y en a pas_

# Contact

**E-mail:** jeanfrancois.grailet@gmail.com
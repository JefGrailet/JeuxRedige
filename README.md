# Comment installer une copie de PAG

_(N.B.: English version below)_

## Etape 1: installation de la base de données
Sur votre serveur, créez une nouvelle base données avec le nom de votre choix, en choisissant 
l'interclassement utf8_unicode_520_ci, puis importez le fichier `pag_db.sql` dans celle-ci. Cette 
base de données contient bien sûr toutes les tables SQL qui sont utilisées par PAG mais aussi 
quelques contenus de base, comme une première liste de jeux et un premier compte d'utilisateur.

## Etape 2: paramétrage du site
Vous devez à présent modifier le fichier `src/config/Config.inc.php` si nécessaire. Il faut en 
effet y renseigner les informations de connexion à la base de données de votre serveur (N.B.: par 
défaut, le code fourni est configuré pour un serveur local).

Notez la présence de deux paramètres supplémentaires à considérer si nécessaire:

* **_paths\_js\_extension_** renseigne l'extension par défaut de vos fichiers JavaScript. Si vous 
  souhaitez utiliser les scripts tels quels, vous n'avez rien à faire. Si en revanche vous 
  souhaitez par exemple les minimiser, faites-le en remplaçant l'extension `.js` par `.min.js`, 
  afin de pouvoir garder les scripts originaux, et modifiez _paths_js_extension_ en conséquence.
* **_protocol_** renseigne le protocole que vous utilisez pour accéder au site, typiquement HTTP 
  ou HTTPS. Ce paramètre doit être modifié (par exemple, "_https_" au lieu de "_http_") si vous 
  comptez utiliser un autre protocole et ce afin de correctement préfixer les URLs absolues vers 
  les images/clips vidéo stockés sur le site ou vers les articles.

**Remarque importante:** le fichier `src/libraries/Header.lib.php` se sert de 
`$_SERVER['DOCUMENT_ROOT']` et `$_SERVER['SERVER_NAME']` pour déterminer automatiquement le chemin 
absolu vers les fichiers du site et l'URL de base. Si votre serveur ne fournit pas de valeurs 
correctes pour ces variables, pensez à éditer les premières lignes de ce fichier.

## Etape 3: copie des fichiers sources
Copiez l'intégralité du contenu du dossier `src` (après les modifications de l'étape 2) dans votre 
dossier `www` (ou équivalent). Le contenu copié reprend également les sous-dossiers d'upload et les 
images qui correspondent au contenu fourni par défaut.

## Etape 4: premier accès
Vous pouvez à présent utiliser votre copie de PAG comme bon vous semble. Pour vous simplifier la 
vie, un premier compte utilisateur est disponible dans la base de données initiale. Les 
identifiants de ce compte sont (les espaces sont compris):

* **Pseudonyme:** _AlainTouring_
* **Mot de passe:** _il n'y en a pas_

# How to install a clone of PAG

## Step 1: setting up the database
On your server, create a new database with a name of your choice, choosing utf8_unicode_520_ci as 
encoding, then import the `pag_db.sql` file inside it. This database contains of course all the 
SQL tables which are used by PAG but also provides sauf default content such as a first list of 
games and a first user account.

## Step 2: configuration of the website
You now have to edit the file `src/config/Config.inc.php` if necessary. Indeed, you have 
to write there the details to connect to your database (N.B.: by default, provided code is 
configured for a local server).

Note that there are two additional parameters you might want to edit too in some cases:

* **_paths\_js\_extension_** provides the default extension of your JavaScript files. If you wish 
  to use them "_as is_", you don't have to do anything. If, on the contrary, you wish (for 
  instance) to minimize them, do so while replacing the usual `.js` extension with `.min.js` in 
  order to keep the original scripts. Then, modify _paths_js_extension_ accordingly.
* **_protocol_** provides the protocol you use to access the website, e.g., HTTP or HTTPS. This 
  parameter must be updated (e.g., "_https_" instead of "_http_") if you intend to use another 
  protocol. This is necessary in order to correctly prefix all absolute URLs towards pictures or 
  video clips stored on the website or towards articles.

**Important remark:** the file `src/libraries/Header.lib.php` relies on 
`$_SERVER['DOCUMENT_ROOT']` and `$_SERVER['SERVER_NAME']` to find automatically the absolute path 
towards the files of the site as well as the base URL. If your server doesn't provide correct 
values for these variables, you might want to edit the first lines of this file.

## Step 3: copy of the source files
Copy the entirety of the `src` folder (after the modifications of step 2) in your `www` folder (or 
equivalent). The copied content also include the upload subfolders and the pictures that are part 
of the default content.

## Step 4: First use
You can now use your PAG copy as you like. To simplify your life, a first user account is 
available in the initial database. The credentials for this account are (blank spaces included):

* **Pseudonym:** _AlainTouring_
* **Password:** _il n'y en a pas_

# Contact

**E-mail:** jeanfrancois.grailet@gmail.com
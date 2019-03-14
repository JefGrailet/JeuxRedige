# Comment installer une copie de PAG

_(N.B.: English version below)_

## Etape 1: installation de la base de données
Sur votre serveur, créez une nouvelle base données avec le nom de votre choix, en choisissant 
l'interclassement utf8_unicode_520_ci, puis importez le fichier `pag_db.sql` dans celle-ci. Cette 
base de données contient bien sûr toutes les tables SQL qui sont utilisées par PAG mais aussi 
quelques contenus de base, comme une première liste de jeux et un premier compte d'utilisateur.

## Etape 2: paramétrage du site
Vous devez à présent modifier sensiblement deux fichiers:
-`Header.lib.php` dans `src/libraries`, 
-`default.js` dans `src/javascript`.

Dans le premier, modifiez la ligne du code de la méthode `init()` de la classe statique `Database` 
afin d'y placer les informations de connexion à la base de données propres à votre serveur (N.B.: 
par défaut, le code fourni est configuré pour un serveur local WampServer). Ensuite, retrouvez les 
constantes `WWW_PATH` et `HTTP_PATH` de la classe `PathHandler` (peu après la 800e ligne de ce 
fichier) pour y placer respectivement le chemin absolu de votre dossier `www` (ou équivalent) sur 
sa machine hôte et la racine de toutes vos URLs.

Dans `default.js`, éditez la variable `.httpPath` dans `DefaultLib` pour y placer à nouveau la 
racine de toutes vos URLs.

## Etape 3: copie du dossier `src`
Copiez l'intégralité du contenu du dossier `src` (après les modifications de l'étape 2) dans votre 
dossier `www` (ou équivalent). Le contenu copié reprend également les sous-dossiers d'upload et les 
images qui correspondent au contenu fourni par défaut.

## Etape 4: premier accès
Vous pouvez à présent utiliser votre copie de PAG comme bon vous semble. Pour vous simplifier la 
vie, un premier compte utilisateur est disponible dans la base de données initiale. Les 
identifiants de ce compte sont (les espaces sont compris):

**Pseudonyme:** _AlainTouring_
**Mot de passe:** _il n'y en a pas_

# How to install a clone of PAG

## Step 1: setting up the database
On your server, create a new database with a name of your choice, choosing utf8_unicode_520_ci as 
encoding, then import the `pag_db.sql` file inside it. This database contains of course all the 
SQL tables which are used by PAG but also provides sauf default content such as a first list of 
games and a first user account.

## Step 2: configuration of the website
You now have to slightly edit two files:
-`Header.lib.php` in `src/libraries`, 
-`default.js` in `src/javascript`.

In the former, edit the line of code in the `init()` method of static class `Database` to provide 
the database connection details of your own server (by default, the provided code is configured 
for a local server WampServer). Then, find the `WWW_PATH` and `HTTP_PATH` constants from the 
`PathHandler` class (shortly after the 800th line) to write in it, respectively, the absolute path 
of your `www` folder (or equivalent) on its host machine and the root of all your URLs.

In the latter, edit the `.httpPath` variable in `DefaultLib` to write once again the root of all 
your URLs.

## Step 3: copy of the `src` folder
Copy the entirety of the `src` folder (after the modifications of step 2) in your `www` folder (or 
equivalent). The copied content also include the upload subfolders and the pictures that are part 
of the default content.

## Step 4: First use
You can now use your PAG copy as you like. To simplify your life, a first user account is 
available in the initial database. The credentials for this account are (blank spaces included):

**Pseudonym:** _AlainTouring_
**Password:** _il n'y en a pas_

# Contact

**E-mail:** jeanfrancois.grailet@gmail.com
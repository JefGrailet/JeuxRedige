(English below)

Comment "installer" une copie de PAG
====================================

1) Installation de la base de donn�es
-------------------------------------
Sur votre serveur, cr�ez une nouvelle base donn�es avec le nom de votre choix, en choisissant 
l'interclassement utf8_unicode_520_ci, puis importez le fichier pag_db.sql dans celle-ci. Cette 
base de donn�es contient bien s�r toutes les tables SQL qui sont utilis�es par PAG mais aussi 
quelques contenus de base, comme une premi�re liste de jeux et un premier compte d'utilisateur.

2) Param�trage du site
----------------------
Vous devez � pr�sent modifier sensiblement deux fichiers:
-Header.lib.php dans src/libraries/, 
-default.js dans src/javascript/.

Dans le premier, modifiez la ligne du code de la m�thode init() de la classe statique Database 
afin d'y placer les informations de connexion � la base de donn�es propres � votre serveur (N.B.: 
par d�faut, le code fourni est configur� pour un serveur local WampServer). Ensuite, retrouvez les 
constantes WWW_PATH et HTTP_PATH de la classe PathHandler (peu apr�s la 800e ligne de ce fichier) 
pour y placer respectivement le chemin absolu de votre dossier www/ (ou �quivalent) sur sa machine 
h�te et la racine de toutes vos URLs.

Dans default.js, �ditez la variable .httpPath dans DefaultLib pour y placer � nouveau la racine de 
toutes vos URLs.

3) Copie du dossier src/
------------------------
Copiez l'int�gralit� du contenu du dossier src/ (apr�s les modifications de l'�tape 2) dans votre 
dossier www/ (ou �quivalent). Le contenu copi� reprend �galement les sous-dossiers d'upload et les 
images qui correspondent au contenu fourni par d�faut.

4) Premier acc�s
----------------
Vous pouvez � pr�sent utiliser votre copie de PAG comme bon vous semble. Pour vous simplifier la 
vie, un premier compte utilisateur est disponible dans la base de donn�es initiale. Les 
identifiants de ce compte sont:

Pseudonyme: "AlainTouring"
Mot de passe: "il n'y en a pas"

(bien entendu, il ne faut pas recopier les guillemets)

------------------------------------

How to "install" a clone of PAG
===============================

1) Setting up the database
--------------------------
On your server, create a new database with a name of your choice, choosing utf8_unicode_520_ci as 
encoding, then import the pag_db.sql file inside it. This database contains of course all the SQL 
tables which are used by PAG but also provides sauf default content such as a first list of games 
and a first user account.

2) Configuration of the website
-------------------------------
You now have to slightly edit two files:
-Header.lib.php in src/libraries/, 
-default.js in src/javascript/.

In the former, edit the line of code in the init() method of stati class Database to provide the 
database connection details of your own server (by default, the provided code is configured for a 
local server WampServer). Then, find the WWW_PATH and HTTP_PATH constants from the PathHandler 
class (shortly after the 800th line) to write in it, respectively, the absolute path of your www/ 
folder (or equivalent) on its host machine and the root of all your URLs.

In the latter, edit the .httpPath variable in DefaultLib to write once again the root of all your 
URLs.

3) Copy of the src/ folder
--------------------------
Copy the entirety of the src/ folder (after the modifications of step 2) in your www/ folder (or 
equivalent). The copied content also include the upload sub-folders and the pictures that are part 
of the default content.

4) First use
------------
You can now use your PAG copy as you like. To simplify your life, a first user account is 
available in the initial database. The credentials for this account are:

Pseudonym: "AlainTouring"
Password: "il n'y en a pas"

(Of course, you don't need to copy the quotes)
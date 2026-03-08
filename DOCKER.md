# JeuxRédige x Docker

## Étapes


**Pré-requis**

Avoir un fichier "Dump.sql" à la racine du projet contenant un dump sql de la base de données de jeuxredige

**Commandes**
- `docker-compose up` : Lance le projet sur le port 80

## Ports
Certains ports ne sont pas définis volontairement pour éviter de provoquer des conflits avec des potentiels conteneurs qui tournent sur l'hôte. Pour les définir, créez le fichier `docker-compose.override.yml` et définissez les ports manquants. Exemple :
```
services:
   php:
      ports:
         - 80:80
   mysql:
      ports:
         - 3307:3306
   phpmyadmin:
      ports:
         - 8080:80
```

## PhpMyAdmin
Un container PhpMyAdmin tourne sur le port 8080. Les accès sont les suivants :
- pseudo : root
- mot de passe : root

<?php

/**
* "About" page; simply displays a small text about the website. The inclusion of the header
* library activates all default features for a user.
*/

require './libraries/Header.lib.php';
require_once './libraries/core/Twig.config.php';

echo $twig->render("about.html.twig", [
   "list_css_files" => ["about"],
   "page_title" => "À propos",
   "selectedLogo" => $twig->getGlobals()["current_category"],
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "À propos - JeuxRédige",
      "description" => "JeuxRédige est, comme son nom l'indique, un site web qui a été conçu comme un support d'écriture pour parler de jeu vidéo de manière générale. Vous pourrez y trouver des critiques, des chroniques ou même des guides sur les jeux vidéo récents comme anciens, avec à l'occasion quelques billets d'humeur, toujours en rapport avec la culture des jeux.",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);

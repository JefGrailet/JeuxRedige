<?php
require_once './vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('./views');

$twig = new \Twig\Environment($loader, [
   'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

$twig->addGlobal("webRoot", PathHandler::HTTP_PATH());
$twig->addGlobal("extJS", PathHandler::JS_EXTENSION());
$twig->addGlobal("JSFiles", "");
$twig->addGlobal("autoJS", "");
$twig->addGlobal("list_categories", [
   "review" => [
      "name" => [
         "singular" => "Critique",
         "plural" => "Critiques",
      ],
      "color" => "#cd301e",
      "empty_message" => "aucune critique",
   ],
   "preview" => [
      "name" => [
         "singular" => "Aperçu",
         "plural" => "Aperçus",
      ],
      "color" => "#34b1e5",
      "empty_message" => "aucun aperçu",
   ],
   "opinion" => [
      "name" => [
         "singular" => "Humeur",
         "plural" => "Humeurs",
      ],
      "color" => "#91c148",
      "empty_message" => "aucune humeur",
   ],
   "chronicle" => [
      "name" => [
         "singular" => "Chronique",
         "plural" => "Chroniques",
      ],
      "color" => "#99368b",
      "empty_message" => "aucune chronique",
   ],
   "guide" => [
      "name" => [
         "singular" => "Guide",
         "plural" => "Guides",
      ],
      "color" => "#dd9302",
      "empty_message" => "aucun guide",
   ],
   "misc" => [
      "name" => [
         "singular" => "Hors-jeu",
         "plural" => "Hors-jeu",
      ],
      "color" => "#969696",
      "empty_message" => "aucun hors-jeu",
   ],
]);
$twig->addGlobal("meta", [
   "title" => "JeuxRédige",
   "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
   "image" => "https://" . $_SERVER["HTTP_HOST"] . "/logos/default_meta_logo.jpg",
   "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
   "full_title" => "",
]);
$twig->addGlobal("dialogs", "");
$twig->addGlobal("page_title", "Site de critiques de jeux vidéo");
$twig->addGlobal("selectedLogo", "default");
$twig->addGlobal("userSide", "default");
$twig->addGlobal("no_custom_logo", false);
$twig->addGlobal("is_user_logged", LoggedUser::isLoggedIn());
if (LoggedUser::isLoggedIn()) {
   $twig->addGlobal("userInfos", array(
      "avatar" => PathHandler::getAvatarMedium(LoggedUser::$data['used_pseudo']),
      "pseudo" => LoggedUser::$data['pseudo'],
      "is_admin" => LoggedUser::$data['function_pseudo'] !== NULL && strlen(LoggedUser::$data['function_pseudo']) > 0 && LoggedUser::$data['function_name'] !== 'alumnus',
      "is_using_admin_account" => LoggedUser::$data['function_pseudo'] === LoggedUser::$data['used_pseudo'],
      "alt_pseudo" => LoggedUser::$data['function_pseudo'],
      "test" => LoggedUser::$data['function_name'] === 'administrator',
   ));
}
$twig->addGlobal("renderTime", number_format(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 5, '.', ""));
parse_str($_SERVER["QUERY_STRING"], $queryString);
$twig->addGlobal("query_string", $queryString);
$twig->addGlobal("current_category", $twig->getGlobals()["query_string"]["article_category"] ?? "default");

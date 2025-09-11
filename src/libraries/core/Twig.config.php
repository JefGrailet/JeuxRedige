<?php
require_once './vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('./views');

$twig = new \Twig\Environment($loader, [
   'debug' => true,
]);
$twig->addExtension(new \Twig\Extension\DebugExtension());

$twig->addGlobal("webRoot", PathHandler::HTTP_PATH());
$twig->addGlobal("configJS", "");
$twig->addGlobal("extJS", PathHandler::JS_EXTENSION());
$twig->addGlobal("JSFiles", "");
$twig->addGlobal("autoJS", "");
$twig->addGlobal("list_categories", [
   "review" => [
      "name" => "Critiques",
      "color" => "#cd301e",
   ],
   "preview" => [
      "name" => "Aperçus",
      "color" => "#34b1e5",
   ],
   "opinion" => [
      "name" => "Humeurs",
      "color" => "#91c148",
   ],
   "chronicle" => [
      "name" => "Chroniques",
      "color" => "#99368b",
   ],
   "guide" => [
      "name" => "Guides",
      "color" => "#dd9302",
   ],
   "misc" => [
      "name" => "Hors-Jeu",
      "color" => "#969696",
   ],
]);
$twig->addGlobal("meta", [
   "title" => "JeuxRédige",
   "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
   "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
   "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
   "full_title" => "",
]);
$twig->addGlobal("dialogs", "");
$twig->addGlobal("page_title", "Site de critiques de jeux vidéo");
$twig->addGlobal("selectedLogo", "default");
$twig->addGlobal("userSide", "default");
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

<?php

/**
 * Home page.
 */

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './model/Topic.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';
require './view/intermediate/TopicThumbnail.ir.php';

require_once './vendor/autoload.php';

WebpageHandler::redirectionAtLoggingIn();


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
$twig->addGlobal("articles_categories", [
   "review" => [
      "name" => "Critique",
   ],
   "preview" => [
      "name" => "Aperçu",
   ],
   "opinion" => [
      "name" => "Humeur",
   ],
   "chronicle" => [
      "name" => "Chronique",
   ],
   "guide" => [
      "name" => "Guide",
   ],
   "misc" => [
      "name" => "Hors-Jeu",
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

// Gets the last featured articles
$articles = null;
try {
   $articles = Article::getFeaturedArticles(8);
} catch (Exception $e) {
   $errorTplInput = array('error' => 'dbError');
   echo $twig->render("index_fail.html.twig", ["error_key" => "dbError"]);
   return;
}

if ($articles == NULL) {
   $errorTplInput = array('error' => 'noContent');
   echo $twig->render("index_fail.html.twig", ["error_key" => "noContent"]);

   return;
}

$NB_MAX_ARTICLES_HIGHLIGHTED = 2;

$listArticlesComputed = array_map(function ($article, $idx) use ($NB_MAX_ARTICLES_HIGHLIGHTED) {
   return array(
      ...$article,
      "is_highlighted" => $idx < $NB_MAX_ARTICLES_HIGHLIGHTED,
      "link" => ArticleThumbnailIR::getLink($article),
      "date_time" => ArticleThumbnailIR::getDateTime($article),
      "thumbnail" => ArticleThumbnailIR::getThumbnail($article),
   );
}, $articles, array_keys($articles));

$listCSSFiles = ["pool"];

echo $twig->render("index.html.twig", [
   "list_articles" => $listArticlesComputed,
   "list_css_files" => ["pool"],
   "meta" => [
      "title" => "JeuxRédige",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);

<?php
/**
 * Home page.
 */

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

require_once './vendor/autoload.php';
require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

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
   "selectedLogo" => "default",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "JeuxRédige",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);

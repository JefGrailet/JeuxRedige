<?php
/**
 * Home page.
 */

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Gets the last featured articles
$articles = null;
try {
   $articles = Article::getFeaturedArticles(8);
} catch (Exception $e) {
   echo $twig->render("errors/error.html.twig", ["error_key" => "dbError"]);
   return;
}

if ($articles == NULL) {
   echo $twig->render("errors/error.html.twig", ["error_key" => "noContent"]);

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

echo $twig->render("index.html.twig", [
   "list_articles" => $listArticlesComputed,
   "list_css_files" => ["pool"],
   "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   "flash_message_extra_data" => isset($_COOKIE['flash_message_extra_data']) ? json_decode($_COOKIE['flash_message_extra_data']) : "",
   "selectedLogo" => "default",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "JeuxRédige",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "full_title" => "",
   ]
]);

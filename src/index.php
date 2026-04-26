<?php
/**
 * Home page.
 */

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './model/rendering/ArticleRendering.class.php';

// TODO: eventually dispose of this
require './view/intermediate/ArticleThumbnail.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Gets the last featured articles
$articles = null;
try {
   $NB_ARTICLES_DISPLAYED = 9;
   $articles = Article::getFeaturedArticles($NB_ARTICLES_DISPLAYED);
} catch (Exception $e) {
   echo $twig->render("errors/error.html.twig", ["error_key" => "dbError"]);
   return;
}

if ($articles == NULL) {
   echo $twig->render("errors/error.html.twig", ["error_key" => "noContent"]);
   return;
}

$NB_MAX_ARTICLES_HIGHLIGHTED = 3;

// Sorts articles depending on whether they have a highlight or not
$highlighted = array();
$checkerboard = array();
for($i = 0; $i < count($articles); $i++)
{
   $highlight = ArticleRendering::getHighlight($articles[$i]["id_article"]);
   if(strlen($highlight) > 0 && count($highlighted) < $NB_MAX_ARTICLES_HIGHLIGHTED)
   {
      $articles[$i]["thumbnail"] = $highlight;
      array_push($highlighted, $articles[$i]);
   }
   else
   {
      $articles[$i]["thumbnail"] = ArticleRendering::getThumbnail($articles[$i]["id_article"]);
      array_push($checkerboard, $articles[$i]);
   }
}
$sorted_articles = array_merge($highlighted, $checkerboard);

$listArticlesComputed = array_map(function ($article, $idx) use ($NB_MAX_ARTICLES_HIGHLIGHTED) {
   return array(
      ...$article,
      "is_highlighted" => $idx < $NB_MAX_ARTICLES_HIGHLIGHTED,
      "link" => PathHandler::articleURL($article),
      "date_time" => Utils::timeToString($article["date_publication"])
   );
}, $sorted_articles, array_keys($sorted_articles));

echo $twig->render("index.html.twig", [
   "list_articles" => $listArticlesComputed,
   "list_css_files" => ["pool"],
   "list_js_files" => ["article-transition"],
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

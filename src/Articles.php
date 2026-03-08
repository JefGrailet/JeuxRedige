<?php

/**
* Script to display all published articles.
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Has a specific category been selected ?
$articlesCategory = ''; // Empty string -> all categories blended together
if(!empty($_GET['article_category']) && in_array($_GET['article_category'], array_keys(Utils::ARTICLES_CATEGORIES)))
   $articlesCategory = Utils::secure($_GET['article_category']);

// Gets the articles
$nbArticles = 0;
$articles = [];
try
{
   $nbArticles = Article::countArticles($articlesCategory, true);

   $currentPage = 1;
   $nbPages = ceil($nbArticles / WebpageHandler::$miscParams['articles_per_page']);
   $firstArticle = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstArticle = ($getPage - 1) * WebpageHandler::$miscParams['articles_per_page'];
      }
   }
   $articles = Article::getArticles($firstArticle, WebpageHandler::$miscParams['articles_per_page'], $articlesCategory, true);
}
catch(Exception $e)
{
   echo $twig->render("errors/error.html.twig", [
      "error_key" => "dbError",
   ]);
   die();
}

$listArticlesComputed = array_map(function ($article) {
   return array(
      ...$article,
      "is_highlighted" => false,
      "link" => ArticleThumbnailIR::getLink($article),
      "date_time" => ArticleThumbnailIR::getDateTime($article),
      "thumbnail" => ArticleThumbnailIR::getThumbnail($article),
   );
}, $articles, array_keys($articles));

$logoCSSFile = null;
if ($twig->getGlobals()["current_category"] != "default") {
   $logoCSSFile = "charter_" . $twig->getGlobals()["current_category"];
}

$currentCategory = $twig->getGlobals()["list_categories"][$twig->getGlobals()["current_category"]]["name"]["plural"] ?? "Articles";
$currentPage = $twig->getGlobals()["query_string"]["page"] ?? "1";

echo $twig->render("list-articles.html.twig", [
   "list_articles" => $listArticlesComputed,
   "list_css_files" => array_filter(["pool", "pagination", "categories", $logoCSSFile], static function($var){return $var !== null;} ),
   "list_js_files" => ["dropdown_redirect"],
   "page_title" => "{$currentCategory} Page {$currentPage}",
   "current_category" => $twig->getGlobals()["current_category"],
   "nb_pages" => $nbPages,
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "{$currentCategory} Page {$currentPage}",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "full_title" => "",
   ]
]);



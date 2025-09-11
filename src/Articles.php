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
$articles = null;
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
   echo $twig->render("articles_fail.html.twig", [
      "error_key" => "dbError",
      "wholeList" => "viewed",
      "research" => "link",
   ]);
}

// // Final HTML code (with page configuration)
// $pageConfig = WebpageHandler::$miscParams['articles_per_page'].'|'.$nbArticles.'|'.$currentPage;
// $pageConfig .= '|./Articles.php?page=[]';
// $catLinks = '';
// if(strlen($articlesCategory) > 0)
// {
//    $pageConfig .= '&article_category='.$articlesCategory;
//    $catLinks = Utils::makeCategoryLinks('Articles.php', $articlesCategory);
// }
// else
//    $catLinks = Utils::makeCategoryLinks('Articles.php');
// $finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails,
//                        'categoriesLinks' => $catLinks, 'research' => 'link');
// $content = TemplateEngine::parse('view/content/ArticlesList.ctpl', $finalTplInput);

/*
 * Extra space for the thumbnails pool when there is not enough articles (in the selected
 * category) to have several pages.
 */

// if ($nbPages == 1)
// {
//    $initialDiv = '<div id="articlesPool">';
//    $withExtraSpace = '<div id="articlesPool" style="margin-top: 20px;">';
//    $content = str_replace($initialDiv, $withExtraSpace, $content);
// }

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

echo $twig->render("articles.html.twig", [
   "list_articles" => $listArticlesComputed,
   "list_css_files" => array_filter(["pool", "categories", $logoCSSFile], static function($var){return $var !== null;} ),
   "page_title" => "Articles",
   "nbPages" => $nbPages,
   "selectedLogo" => $twig->getGlobals()["current_category"],
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "JeuxRédige",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);



<?php

/**
* Script to handle user's articles ("My articles" page).
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in
if(!LoggedUser::isLoggedIn())
{
   echo $twig->render("errors/error.html.twig", [
      "page_title" => "Erreur",
      "error_key" => "notConnected",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur",
         "description" => "Erreur",
         "full_title" => "",
      ]
   ]);

   die();
}

// Gets the articles
$nbArticles = 0;
$articles = null;
$filterName = isset($_GET["type"]) ? $_GET["type"] : "";

try
{
   $nbArticles = Article::countMyArticles($filterName);

   $currentPage = 1;
   $nbItemsPerPage = WebpageHandler::$miscParams['articles_per_page'];
   $nbPages = ceil($nbArticles / $nbItemsPerPage);
   $firstArticle = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstArticle = ($getPage - 1) * $nbItemsPerPage;
      }
   }
   $articles = Article::getMyArticles($firstArticle, $nbItemsPerPage, $filterName);
}
catch(Exception $e)
{
   echo $twig->render("errors/error.html.twig", [
      "error_title" => "Impossible d'atteindre vos articles",
      "error_key" => "MyArticles",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur - Serveur erreur",
         "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
         "full_title" => "",
      ]
   ]);
   die();
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of thumbnails. */

// Rendered thumbnails
$thumbnails = '';
$fullInput = array();
for($i = 0; $i < count($articles); $i++)
{
   $intermediate = ArticleThumbnailIR::process($articles[$i], true, false);
   array_push($fullInput, $intermediate);
}

// Displays the produced page
$listArticlesComputed = array_map(function ($article) {
   return array(
      ...$article,
      "link" => ArticleThumbnailIR::getLink($article, true),
      "date_time" => ArticleThumbnailIR::getDateTime($article),
      "thumbnail" => ArticleThumbnailIR::getThumbnail($article),
      "status" => ArticleThumbnailIR::getStatus($article),
   );
}, $articles, array_keys($articles));

echo $twig->render("articles_user.html.twig", [
   "page_title" => "Mes articles",
   "list_css_files" => ["pool", "pagination", "articles_filter"],
   "list_articles" => $listArticlesComputed,
   "nb_articles" => $nbArticles,
   "nb_pages" => $nbPages,
   "query_string" => [
      "filter_name" => $filterName,
      "page" => intval($_GET['page'] ?? "1"),
   ],
   "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Mes articles",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "full_title" => "",
   ]
]);

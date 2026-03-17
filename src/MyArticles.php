<?php

/**
* Script to handle user's articles ("My articles" page).
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './model/rendering/ArticleRendering.class.php';

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

// Displays the produced page
$listArticlesComputed = array_map(function ($article) {
   $output = array(
      ...$article,
      "link" => PathHandler::HTTP_PATH() . 'EditArticle.php?id_article='.$article["id_article"],
      "thumbnail" => ArticleRendering::getThumbnail($article["id_article"]),
      "status" => "wip",
   );
   if($article["date_publication"] !== "1970-01-01 00:00:00")
   {
      $output["date_time"] = Utils::timeToString($article["date_publication"]);
      $output["status"] = "published";
   }
   return $output;
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

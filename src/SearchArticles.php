<?php

/**
* Search engine for articles, based on keywords.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './model/Article.class.php';
require './model/rendering/ArticleRendering.class.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addJS('keywords');
WebpageHandler::noContainer();

// Base Twig input
$twig_input = [
   "list_css_files" => ["pool", "select2.min", "form_search"],
   "list_js_files" => ["libs/select2.min", "libs/select2.fr.min", "keywords_v2"],
   "page_title" => "Rechercher des articles",
   "no_custom_logo" => true,
   "selectedLogo" => $twig->getGlobals()["current_category"],
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Rechercher des articles",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "full_title" => "",
   ]
];

// Keywords can be provided either as $_POST either as $_GET; $_POST has priority
$getKeywords = [];
$gotInput = false;
if(!empty($_POST['keywords']) || !empty($_GET['keywords']))
{
   $gotInput = true;
   if(!empty($_POST['keywords']))
      $getKeywords = $_POST['keywords'];
   else if(!empty($_GET['keywords']))
      $getKeywords = $_GET['keywords'];
}

if($gotInput)
{
   // Has a specific (and valid) category been selected ?
   $artCategory = ''; // Empty string -> all categories blended together
   if(!empty($_GET['article_category']) || !empty($_POST['article_category']))
   {
      if (!empty($_GET['article_category']))
         $artCategory = Utils::secure($_GET['article_category']);
      else
         $artCategory = Utils::secure($_POST['article_category']);
      if (!in_array($artCategory, array_keys(Utils::ARTICLES_CATEGORIES)))
         $artCategory = '';
   }

   // Option for strict research (i.e. all keywords are found) which can be deactivated
   $strict = false;
   if(!empty($_POST['strict']) || !empty($_GET['strict']))
   {
      $strict = true;
      // TODO
   }

   for ($i=0; $i < count($getKeywords); $i++)
      $getKeywords[$i] = Utils::secure(urldecode($getKeywords[$i]));

   // For additionnal security (especially with $_GET values), we inspect $getKeywords
   $newArray = array();
   for($i = 0; $i < count($getKeywords) && $i < 10; $i++)
   {
      if($getKeywords[$i] === '')
         continue;

      $k = str_replace('"', '', $getKeywords[$i]);
      array_push($newArray, $k);
   }
   $getKeywords = $newArray;

   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   try
   {
      $nbResults = Article::countArticlesWithKeywords($getKeywords, $artCategory, $strict);
      if($nbResults == 0)
      {
         $twig_input["search_error"] = "Votre recherche n'a donné aucun résultat.";
         echo $twig->render("search-articles.html.twig", $twig_input);
         exit();
      }

      // Pagination + gets the results (with user's preferences)
      $currentPage = 1;
      $nbPages = ceil($nbResults / $perPage);
      $firstArt = 0;
      if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
      {
         $getPage = intval($_GET['page']);
         if($getPage <= $nbPages)
         {
            $currentPage = $getPage;
            $firstArt = ($getPage - 1) * $perPage;
         }
      }
      $articles = Article::getArticlesWithKeywords($getKeywords, $firstArt, $perPage, $artCategory, $strict);

      $twig_input["page_title"] = "Rechercher des articles (page {$currentPage})";

      // Now, we can render the thumbnails of the current page
      $twig_input["list_articles"] = array_map(function ($article) {
         return array(
            ...$article,
            "is_highlighted" => false,
            "link" => PathHandler::articleURL($article),
            "date_time" => Utils::timeToString($article["date_publication"]),
            "thumbnail" => ArticleRendering::getThumbnail($article["id_article"])
         );
      }, $articles, array_keys($articles));
   }
   catch(Exception $e)
   {
      $twig_input["search_error"] = "Votre recherche n'a donné aucun résultat suite à un ";
      $twig_input["search_error"] .= "problème de lecture de la base de données.";
      echo $twig->render("search-articles.html.twig", $twig_input);
      exit();
   }
}

echo $twig->render("search-articles.html.twig", $twig_input);

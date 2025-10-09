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
   $errorTplInput = array('error' => 'login');
   $tpl = TemplateEngine::parse('view/user/Pings.fail.ctpl', $errorTplInput); // Can be safely re-used, no ambiguity
   WebpageHandler::wrap($tpl, 'Vous devez être connecté pour éditer vos articles');
}

// Gets the articles
$nbArticles = 0;
$articles = null;
try
{
   $nbArticles = Article::countMyArticles();
   if($nbArticles == 0)
   {
      $errorTplInput = array('error' => 'noArticle');
      $tpl = TemplateEngine::parse('view/user/MyArticles.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Mes articles');
   }

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
   $articles = Article::getMyArticles($firstArticle, WebpageHandler::$miscParams['articles_per_page']);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/user/MyArticles.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre vos articles');
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

// WebpageHandler::wrap($content, 'Mes articles');

echo $twig->render("articles_user.html.twig", [
   "page_title" => "Mes articles",
   "list_css_files" => ["pool"],
   "list_articles" => $listArticlesComputed,
   "nb_articles" => $nbArticles,
   "nb_pages" => $nbPages,
   "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Mes articles",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);

<?php

/**
* Script to handle user's articles ("My articles" page).
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('error' => 'login');
   $tpl = TemplateEngine::parse('view/user/Pings.fail.ctpl', $errorTplInput); // Can be safely re-used, no ambiguity
   WebpageHandler::wrap($tpl, 'Vous devez être connecté pour éditer vos articles');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::noContainer();

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

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/content/ArticleThumbnail.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $errorTplInput = array('error' => 'wrongTemplating');
      $tpl = TemplateEngine::parse('view/user/MyArticles.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre vos articles');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $thumbnails .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['articles_per_page'].'|'.$nbArticles.'|'.$currentPage;
$pageConfig .= '|./MyArticles.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails);
$content = TemplateEngine::parse('view/user/MyArticles.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Mes articles');

?>

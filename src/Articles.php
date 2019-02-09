<?php

/**
* Script to display all published articles.
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::addCSS('pool');
WebpageHandler::noContainer();

// Gets the articles
$nbArticles = 0;
$articles = null;
try
{
   $nbArticles = Article::countPublishedArticles();
   if($nbArticles == 0)
   {
      $errorTplInput = array('error' => 'noArticle', 'wholeList' => 'viewed', 'research' => 'link');
      $tpl = TemplateEngine::parse('view/content/ArticlesList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Articles');
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
   $articles = Article::getPublishedArticles($firstArticle, WebpageHandler::$miscParams['articles_per_page']);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError', 'wholeList' => 'viewed', 'research' => 'link');
   $tpl = TemplateEngine::parse('view/content/ArticlesList.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les articles');
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of thumbnails. */

// Rendered thumbnails
$thumbnails = '';
$fullInput = array();
for($i = 0; $i < count($articles); $i++)
{
   $intermediate = ArticleThumbnailIR::process($articles[$i]);
   array_push($fullInput, $intermediate);
}

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/content/ArticleThumbnail.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $errorTplInput = array('error' => 'wrongTemplating', 'wholeList' => 'viewed', 'research' => 'link');
      $tpl = TemplateEngine::parse('view/content/ArticlesList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les articles');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $thumbnails .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['articles_per_page'].'|'.$nbArticles.'|'.$currentPage;
$pageConfig .= '|./Articles.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails, 
                       'wholeList' => 'viewed', 'research' => 'link');
$content = TemplateEngine::parse('view/content/ArticlesList.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Articles');

?>

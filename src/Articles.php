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

// Has a specific category been selected ?
$artCategory = ''; // Empty string -> all categories blended together
if(!empty($_GET['article_category']) && in_array($_GET['article_category'], array_keys(Utils::ARTICLES_CATEGORIES)))
   $artCategory = Utils::secure($_GET['article_category']);

// Gets the articles
$nbArticles = 0;
$articles = null;
try
{
   $nbArticles = Article::countArticles($artCategory, true);
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
   $articles = Article::getArticles($firstArticle, WebpageHandler::$miscParams['articles_per_page'], $artCategory, true);
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
$catLinks = '';
if(strlen($artCategory) > 0)
{
   $pageConfig .= '&article_category='.$artCategory;
   $catLinks = Utils::makeCategoryLinks('Articles.php', $artCategory);
}
else
   $catLinks = Utils::makeCategoryLinks('Articles.php');
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails, 
                       'categoriesLinks' => $catLinks, 'research' => 'link');
$content = TemplateEngine::parse('view/content/ArticlesList.ctpl', $finalTplInput);

/*
 * Extra space for the thumbnails pool when there is not enough articles (in the selected 
 * category) to have several pages.
 */

if ($nbPages == 1)
{
   $initialDiv = '<div id="articlesPool">';
   $withExtraSpace = '<div id="articlesPool" style="margin-top: 20px;">';
   $content = str_replace($initialDiv, $withExtraSpace, $content);
}

// Displays the produced page
WebpageHandler::wrap($content, 'Articles');

?>

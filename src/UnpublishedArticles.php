<?php

/**
* Script to display unpublished articles to an authorized user. The code is very similar to 
* Articles.php (and uses the same class, functions and templates) but with some minor changes, 
* notably to ensure only authorized logged users can view content that has not been published yet.
*/

require './libraries/Header.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not allowed to edit articles
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$data['can_edit_all_posts']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

WebpageHandler::addCSS('pool');
WebpageHandler::noContainer();

 // For now, cannot browse unpublished articles based on category (may be easily added later)
$artCategory = '';

// Gets the articles
$nbArticles = 0;
$articles = null;
try
{
   $nbArticles = Article::countArticles($artCategory, false);
   if($nbArticles == 0)
   {
      $errorTplInput = array('error' => 'noArticle', 'wholeList' => 'link', 'research' => 'none');
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
   $articles = Article::getArticles($firstArticle, WebpageHandler::$miscParams['articles_per_page'], $artCategory, false);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError', 'wholeList' => 'link', 'research' => 'none');
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
   $intermediate = ArticleThumbnailIR::process($articles[$i], true, true);
   array_push($fullInput, $intermediate);
}

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/content/ArticleThumbnail.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $errorTplInput = array('error' => 'wrongTemplating', 'wholeList' => 'link', 'research' => 'none');
      $tpl = TemplateEngine::parse('view/content/ArticlesList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les articles');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $thumbnails .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['articles_per_page'].'|'.$nbArticles.'|'.$currentPage;
$pageConfig .= '|./UnpublishedArticles.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails, 
                       'categoriesLinks' => '', 'research' => 'goBack');
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

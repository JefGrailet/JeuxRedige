<?php

/**
* Search engine for articles, based on keywords.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './model/Article.class.php';
require './view/intermediate/ArticleThumbnail.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addJS('keywords');
WebpageHandler::noContainer();

// Input for the form
$formData = array('strict' => '',
'article_category' => Utils::makeCategoryChoice(true), 
'keywordsList' => '',
'keywords' => '',
'permanentLink' => '',
'specialMessage' => '',
'showButtons' => 'ok');

// Keywords can be provided either as $_POST either as $_GET; $_POST has priority
$getKeywords = '';
$gotInput = false;
if(!empty($_POST['keywords']) || !empty($_GET['keywords']))
{
   $gotInput = true;
   if(!empty($_POST['keywords']))
      $getKeywords = $_POST['keywords'];
   else if(!empty($_GET['keywords']))
      $getKeywords = urldecode($_GET['keywords']);
}

// Form sent, but no keyword
if(!empty($_POST['sent']) && strlen($getKeywords) == 0)
{
   $formData['specialMessage'] = 'emptyField';
   $tpl = TemplateEngine::parse('view/content/SearchArticles.form.ctpl', $formData);
   WebpageHandler::wrap($tpl, 'Rechercher des articles');
}
// Will search topics and display them
else if($gotInput)
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
      else
         $formData['article_category'] = $artCategory.'||'.$formData['article_category'];
   }
   
   // Option for strict research (i.e. all keywords are found) which can be deactivated
   $strict = false;
   if(!empty($_POST['strict']) || !empty($_GET['strict']))
   {
      $strict = true;
      $formData['strict'] = 'ok';
   }

   $formData['keywords'] = Utils::secure($getKeywords);
   $keywordsArr = explode('|', $formData['keywords']);
   
   // For additionnal security (especially with $_GET values), we inspect $keywordsArr
   $newArray = array();
   for($i = 0; $i < count($keywordsArr) && $i < 10; $i++)
   {
      if($keywordsArr[$i] === '')
         continue;
      
      $k = str_replace('"', '', $keywordsArr[$i]);
      array_push($newArray, $k);
   }
   $keywordsArr = $newArray;
   
   $formData['keywordsList'] = Keywords::display($keywordsArr);
   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   
   try
   {
      $nbResults = Article::countArticlesWithKeywords($keywordsArr, $artCategory, $strict);
      if($nbResults == 0)
      {
         $formData['specialMessage'] = 'noResult';
         $tpl = TemplateEngine::parse('view/content/SearchArticles.form.ctpl', $formData);
         WebpageHandler::wrap($tpl, 'Rechercher des articles');
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
      $results = Article::getArticlesWithKeywords($keywordsArr, $firstArt, $perPage, $artCategory, $strict);
      
      // Now, we can render the thumbnails of the current page
      $thumbnails = '';
      $fullInput = array();
      for($i = 0; $i < count($results); $i++)
         array_push($fullInput, ArticleThumbnailIR::process($results[$i]));
      
      if(count($fullInput) > 0)
      {
         $fullOutput = TemplateEngine::parseMultiple('view/content/ArticleThumbnail.ctpl', $fullInput);
         if(TemplateEngine::hasFailed($fullOutput))
            WebpageHandler::wrap($fullOutput, "Un problème est survenu");
         
         for($i = 0; $i < count($fullOutput); $i++)
            $thumbnails .= $fullOutput[$i];
      }
      
      // Permanent link to this research
      $permaLink = './SearchArticles.php?keywords='.urlencode($formData['keywords']);
      if(strlen($artCategory) > 0)
         $permaLink .= '&article_category='.$artCategory;
      if($strict)
         $permaLink .= '&strict=ok';
      $truePermaLink = $permaLink.'&page='.$currentPage;
      $truePermaLink = '<a href="'.$truePermaLink.'">Lien permanent</a>'."\n";
      
      // Page configuration
      $pageConfig = $perPage.'|'.$nbResults.'|'.$currentPage;
      $pageConfig .= '|'.$permaLink.'&page=[]';
      
      // HTML code; colored links nullified here since article category is selected with the form
      $contInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails, 
                         'categoriesLinks' => '', 'research' => 'goBack');
      $content = TemplateEngine::parse('view/content/ArticlesList.ctpl', $contInput);
      
      // Concats that with the form (+ the current input)
      $formData['permanentLink'] = $truePermaLink;
      $formData['showButtons'] = '';
      $form = TemplateEngine::parse('view/content/SearchArticles.form.ctpl', $formData);
      WebpageHandler::wrap($form."\n<br/>\n".$content, 'Résultats de votre recherche');
   }
   catch(Exception $e)
   {
      $formData['specialMessage'] = 'dbError';
      $tpl = TemplateEngine::parse('view/content/SearchArticles.form.ctpl', $formData);
      WebpageHandler::wrap($tpl."\n<br/>\n".$tpl2, 'Impossible de rechercher des articles');
   }
}
// Form without any article displayed, as there is no input keyword
else
{
   $tpl = TemplateEngine::parse('view/content/SearchArticles.form.ctpl', $formData);
   WebpageHandler::wrap($tpl, 'Rechercher des articles');
}

?>

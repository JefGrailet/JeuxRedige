<?php

/*
* Script to display the complete set of lists (with pagination) created by this user.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// User can only access this part when logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addCSS('media');
WebpageHandler::noContainer();

require './model/GamesList.class.php';
require './view/intermediate/ListThumbnail.ir.php';

// Gets the amount of lists created by this user and the lists to display in the current page.
$nbLists = 0;
$lists = null;
try
{
   $nbLists = GamesList::countMyLists();

   if($nbLists == 0)
   {
      $errorTplInput = array('error' => 'noList');
      $tpl = TemplateEngine::parse('view/user/MyLists.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Mes listes');
   }
   
   $currentPage = 1;
   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   $nbPages = ceil($nbLists / $perPage);
   $firstList = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstList = ($getPage - 1) * $perPage;
      }
   }
   
   $lists = GamesList::getMyLists($firstList, $perPage);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/user/MyLists.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Mes listes');
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of lists. */

// Rendered list thumbnails
$thumbnails = '';
for($i = 0; $i < count($lists); $i++)
{
   $intermediate = ListThumbnailIR::process($lists[$i], false);
   $thumbnail = TemplateEngine::parse('view/content/ListThumbnail.ctpl', $intermediate);
   if(TemplateEngine::hasFailed($thumbnail))
   {
      $errorTplInput = array('error' => 'wrongTemplating');
      $tpl = TemplateEngine::parse('view/user/MyLists.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Mes listes');
   }
   $thumbnails .= $thumbnail;
}

// Final HTML code (with page configuration)
$pageConfig = $perPage.'|'.$nbLists.'|'.$currentPage;
$pageConfig .= '|./MyLists.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails);
$content = TemplateEngine::parse('view/user/MyLists.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Mes listes');

?>

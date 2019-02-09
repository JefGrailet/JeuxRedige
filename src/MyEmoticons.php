<?php

/**
* User page to handle and create new emoticons.
*/

require './libraries/Header.lib.php';
require './model/Emoticon.class.php';
require './model/User.class.php';
require './view/intermediate/EmoticonThumbnail.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('error' => 'login');
   $tpl = TemplateEngine::parse('view/user/Pings.fail.ctpl', $errorTplInput); // Can be safely re-used, no ambiguity
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addJS('emoticons');
WebpageHandler::noContainer();

// Filter
$filter = 'myEmoticons';
if(!empty($_GET['filter']))
{
   switch($_GET['filter'])
   {
      case 'global':
         $filter = 'library';
         break;
      default:
         break;
   }
}

// Prepares the common input for the templates that can be used
$commonTplInput = array('myEmoticons' => 'viewed',
'emoticonsLibrary' => 'link',
'newEmoticonDialog' => '');

if(Utils::check(LoggedUser::$data['can_upload']))
{
   $commonTplInput['newEmoticonDialog'] = 'yes';
}

$pageTitle = 'Mes émoticônes';
if($filter === 'library')
{
   $commonTplInput['myEmoticons'] = 'link';
   $commonTplInput['emoticonsLibrary'] = 'viewed';
   $pageTitle = 'Librairie d\'émoticônes';
}

// Dialogs for creating emoticons or handling those already displayed (useful in any case)
$dialogs = '';
$dialogsTpl = TemplateEngine::parse('view/dialog/Emoticons.multiple.ctpl');
if(!TemplateEngine::hasFailed($dialogsTpl))
   $dialogs = $dialogsTpl;

// Gets the emoticons according to the current page and filter (also deals with possible errors)
$nbEmoticons = 0;
$emoticons = null;
try
{
   if($filter === 'myEmoticons')
      $nbEmoticons = Emoticon::countMyEmoticons();
   else
      $nbEmoticons = Emoticon::countEmoticons();

   if($nbEmoticons == 0)
   {
      if($filter === 'myEmoticons')
         $errorTplInput = array_merge(array('error' => 'noEmoticon1'), $commonTplInput);
      else
         $errorTplInput = array_merge(array('error' => 'noEmoticon2'), $commonTplInput);
      $tpl = TemplateEngine::parse('view/user/EmoticonsList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, $pageTitle, $dialogs);
   }
   
   $currentPage = 1;
   $nbPages = ceil($nbEmoticons / WebpageHandler::$miscParams['emoticons_per_page']);
   $firstEmoticon = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstEmoticon = ($getPage - 1) * WebpageHandler::$miscParams['emoticons_per_page'];
      }
   }
   if($filter === 'myEmoticons')
      $emoticons = Emoticon::getMyEmoticons($firstEmoticon, WebpageHandler::$miscParams['emoticons_per_page']);
   else
      $emoticons = Emoticon::getEmoticons($firstEmoticon, WebpageHandler::$miscParams['emoticons_per_page']);
}
catch(Exception $e)
{
   $errorTplInput = array_merge(array('error' => 'dbError'), $commonTplInput);
   $tpl = TemplateEngine::parse('view/user/EmoticonsList.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les émoticônes', $dialogs);
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of thumbnails. */

// Rendered thumbnails
$thumbnails = '';
$fullInput = array();
for($i = 0; $i < count($emoticons); $i++)
{
   $intermediate = EmoticonThumbnailIR::process($emoticons[$i]);
   array_push($fullInput, $intermediate);
}

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/user/Emoticon.item.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $errorTplInput = array_merge(array('error' => 'wrongTemplating'), $commonTplInput);
      $tpl = TemplateEngine::parse('view/user/EmoticonsList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les émoticônes');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $thumbnails .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['emoticons_per_page'].'|'.$nbEmoticons.'|'.$currentPage;
$pageConfig .= '|./MyEmoticons.php?';
if($filter === 'library')
   $pageConfig .= 'filter=library&';
$pageConfig .= 'page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails);
$finalTplInput = array_merge($finalTplInput, $commonTplInput);
$content = TemplateEngine::parse('view/user/EmoticonsList.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, $pageTitle, $dialogs);

?>

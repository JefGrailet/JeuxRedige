<?php

/*
* Script to display the list of tropes registered in the DB, in alphabetical order. Contrary to 
* games list (currently), tropes can be consulted by anyone.
*/

require './libraries/Header.lib.php';
require './model/Trope.class.php';
require './view/intermediate/Trope.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Temporar "protection": the page can only be consulted by logged users
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tpl = TemplateEngine::parse('view/content/Protected.ctpl');
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addCSS('media');
WebpageHandler::addJS('tropes_pool');
WebpageHandler::noContainer();

// Dialog for deleting a trope (to restrict to authorized users later)
$dialogs = '';
$dialogsTpl = TemplateEngine::parse('view/dialog/DeleteTrope.dialog.ctpl');
if(!TemplateEngine::hasFailed($dialogsTpl))
   $dialogs = $dialogsTpl;

// Gets the amount of tropes and the tropes in the current page.
$nbTropes = 0;
try
{
   $nbTropes = Trope::countTropes();

   if($nbTropes == 0)
   {
      $errorTplInput = array('error' => 'noTrope');
      $tpl = TemplateEngine::parse('view/content/Tropes.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Liste des codes vidéoludiques');
   }
   
   $currentPage = 1;
   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   $nbPages = ceil($nbTropes / $perPage);
   $firstTrope = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstTrope = ($getPage - 1) * $perPage;
      }
   }
   
   $tropes = Trope::getTropes($firstTrope, $perPage);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/content/Tropes.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Liste des codes vidéoludiques');
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of tropes. */

// Rendered tropes
$thumbnails = '';
for($i = 0; $i < count($tropes); $i++)
{
   $intermediate = TropeIR::process($tropes[$i], true, false);
   $thumbnail = TemplateEngine::parse('view/content/Trope.ctpl', $intermediate);
   if(TemplateEngine::hasFailed($thumbnail))
   {
      $errorTplInput = array('error' => 'wrongTemplating');
      $tpl = TemplateEngine::parse('view/content/Tropes.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Liste des codes vidéoludiques');
   }
   $thumbnails .= $thumbnail;
}

// Final HTML code (with page configuration)
$pageConfig = $perPage.'|'.$nbTropes.'|'.$currentPage;
$pageConfig .= '|./Tropes.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails);
$content = TemplateEngine::parse('view/content/Tropes.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Liste des codes vidéoludiques', $dialogs);

?>

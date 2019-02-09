<?php

/**
* Search engine for games, based on tropes.
*/

require './libraries/Header.lib.php';
require './libraries/Keywords.lib.php';
require './model/Game.class.php';
require './view/intermediate/GameLight.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addCSS('media');
WebpageHandler::addJS('tropes');
WebpageHandler::noContainer();

// Input for the form
$formData = array('mode' => 'popular,Recherche par popularité|strict,Recherche par pertinence',
'tropesList' => '',
'tropes' => '',
'permanentLink' => '',
'specialMessage' => '',
'showButtons' => 'ok');

// Keywords can be provided either as $_POST either as $_GET; $_POST has priority
$getTropes = '';
$gotInput = false;
if(!empty($_POST['tropes']) || !empty($_GET['tropes']))
{
   $gotInput = true;
   if(!empty($_POST['tropes']))
      $getTropes = $_POST['tropes'];
   else if(!empty($_GET['tropes']))
      $getTropes = urldecode($_GET['tropes']);
}

// Form sent, but no keyword
if(!empty($_POST['sent']) && strlen($getTropes) == 0)
{
   $formData['specialMessage'] = 'emptyField';
   $tpl = TemplateEngine::parse('view/content/SearchGames.form.ctpl', $formData);
   WebpageHandler::wrap($tpl, 'Rechercher des jeux');
}
// Will search topics and display them
else if($gotInput)
{
   // Option for strict research (i.e. all keywords are found) which can be deactivated
   $strict = false;
   if(!empty($_POST['mode']) && $_POST['mode'] === 'strict')
   {
      $strict = true;
      $formData['mode'] = 'strict||'.$formData['mode'];
   }
   else if(!empty($_GET['mode']) && $_GET['mode'] === 'strict')
   {
      $strict = true;
      $formData['mode'] = 'strict||'.$formData['mode'];
   }

   $formData['tropes'] = Utils::secure($getTropes);
   $tropesArr = explode('|', $formData['tropes']);
   
   // For additionnal security (especially with $_GET values), we inspect $tropesArr
   $newArray = array();
   for($i = 0; $i < count($tropesArr) && $i < 10; $i++)
   {
      if($keywordsArr[$i] === '')
         continue;
      
      $t = str_replace('"', '', $tropesArr[$i]);
      array_push($newArray, $t);
   }
   $tropesArr = $newArray;
   
   $formData['tropesList'] = Keywords::displayTropes($tropesArr);
   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   
   try
   {
      $nbResults = Game::countGamesWithTropes($tropesArr, $strict);
      if($nbResults == 0)
      {
         $formData['specialMessage'] = 'noResult';
         $tpl = TemplateEngine::parse('view/content/SearchGames.form.ctpl', $formData);
         WebpageHandler::wrap($tpl, 'Rechercher des jeux');
      }

      // Pagination + gets the results (with user's preferences)
      $currentPage = 1;
      $nbPages = ceil($nbResults / $perPage);
      $firstGame = 0;
      if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
      {
         $getPage = intval($_GET['page']);
         if($getPage <= $nbPages)
         {
            $currentPage = $getPage;
            $firstGame = ($getPage - 1) * $perPage;
         }
      }
      $results = Game::getGamesWithTropes($tropesArr, $firstGame, $perPage, $strict);
      
      // Now, we can render the thumbnails of the current page
      $thumbnails = '';
      $fullInput = array();
      for($i = 0; $i < count($results); $i++)
         array_push($fullInput, GameLightIR::process($results[$i]));
      
      if(count($fullInput) > 0)
      {
         $fullOutput = TemplateEngine::parseMultiple('view/content/GameLight.ctpl', $fullInput);
         if(TemplateEngine::hasFailed($fullOutput))
            WebpageHandler::wrap($fullOutput, "Un problème est survenu");
         
         for($i = 0; $i < count($fullOutput); $i++)
            $thumbnails .= $fullOutput[$i];
      }
      
      // Permanent link to this research
      $permaLink = './SearchGames.php?tropes='.urlencode($formData['tropes']);
      if($strict)
         $permaLink .= '&mode=strict';
      $permaLink .= '&page=';
      $truePermaLink = $permaLink.$currentPage;
      $truePermaLink = '<a href="'.$truePermaLink.'">Lien permanent (précédente recherche)</a>'."\n";
      
      // HTML code (with page configuration) containing the results
      $pageConfig = $perPage.'|'.$nbResults.'|'.$currentPage;
      $pageConfig .= '|'.$permaLink.'[]';
      $contInput = array('pageConfig' => $pageConfig, 
                         'thumbnails' => $thumbnails, 
                         'searchButton' => '', 
                         'newGameButton' => (LoggedUser::isLoggedIn()) ? 'yes' : '');
      $content = TemplateEngine::parse('view/content/Games.ctpl', $contInput);
      
      // Concats that with the form (+ the current input)
      $formData['permanentLink'] = $truePermaLink;
      $formData['showButtons'] = '';
      $form = TemplateEngine::parse('view/content/SearchGames.form.ctpl', $formData);
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
   $tpl = TemplateEngine::parse('view/content/SearchGames.form.ctpl', $formData);
   WebpageHandler::wrap($tpl, 'Rechercher des jeux');
}

?>

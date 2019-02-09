<?php

/*
* Script to write a piece of trivia, i.e., a short story about a game explaining its origins, 
* development, etc. that's interesting but not worthy of a full article.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './model/Game.class.php';
require './model/Trivia.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in either not allowed to create content
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$fullData['advanced_features']))
{
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

// Webpage settings
WebpageHandler::addCSS('content_edition');
WebpageHandler::addJS('formatting');
WebpageHandler::addJS('content_editor');
WebpageHandler::addJS('games'); // For game selection

$dialogs = '';
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs .= $formattingDialogsTpl;

// Content of the main form
$formData = array('errors' => '', 
'displayedGame' => '', 
'title' => '',
'content' => '', 
'game' => '');

/****** PRE-PROCESSING FORM ******/

// Game name might be in the URL, otherwise the form will ask the name of the evaluated game
if(!empty($_GET['game']))
{
   $gameTitle = Utils::secure(urldecode($_GET['game']));
   try
   {
      $preselectedGame = new Game($gameTitle);
      $formData['displayedGame'] = 'chosen||'.$gameTitle.'|'.PathHandler::gameURL($preselectedGame->getAll());
      $formData['game'] = $gameTitle;
   }
   catch(Exception $e)
   {
      $formData['displayedGame'] = 'pick';
   }
}
else
{
   $formData['displayedGame'] = 'pick';
}

/****** END OF PRE-PROCESSING ******/

// Form treatment directly starts here, as there are no pre-requisite
if(!empty($_POST['sent']))
{
   $formData['title'] = Utils::secure($_POST['title']);
   $formData['content'] = Utils::secure($_POST['message']);
   $formData['game'] = Utils::secure($_POST['game']);
   
   $validGame = false;
   $selectedGame = null;
   try
   {
      $selectedGame = new Game($formData['game']);
      $validGame = true;
   }
   catch(Exception $e) { }
   
   // Various possible errors
   if(strlen($formData['game']) == 0 || strlen($formData['title']) == 0 || strlen($formData['content']) == 0)
      $formData['errors'] .= 'emptyFields|';
   if(strlen($formData['title']) > 60)
      $formData['errors'] .= 'titleTooLong|';
   if(strlen($formData['game']) > 0 && !$validGame)
      $formData['errors'] .= 'invalidGame|';
   
   if(strlen($formData['errors']) == 0)
   {
      $newPiece = null;
      try
      {
         $newPiece = Trivia::insert($selectedGame->get('tag'), 
                                    $formData['title'], 
                                    FormParsing::parse($formData['content']));
      }
      catch(Exception $e)
      {
         // Completing some fields
         if(strlen($formData['game']) > 0 && $validGame)
            $formData['displayedGame'] = 'chosen||'.$formData['game'].'|'.PathHandler::gameURL($selectedGame->getAll());
         else
            $formData['displayedGame'] = 'pick';
         
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewTrivia.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Rédiger une nouvelle anecdote', $dialogs);
      }
      
      // Redirection
      $newPieceURL = PathHandler::triviaURL($newPiece->getAll());
      header('Location:'.$newPieceURL);
      
      // Success page
      $tplInput = array('target' => $newPieceURL);
      $successPage = TemplateEngine::parse('view/content/NewContent.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Nouvelle anecdote pour le jeu "'.$selectedGame->get('tag').'"');
   }
   else
   {
      // Completing some fields
      if(strlen($formData['game']) > 0 && $validGame)
         $formData['displayedGame'] = 'chosen||'.$formData['game'].'|'.PathHandler::gameURL($selectedGame->getAll());
      else
         $formData['displayedGame'] = 'pick';
      
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewTrivia.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Rédiger une nouvelle anecdote', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/NewTrivia.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Rédiger une nouvelle anecdote', $dialogs);
}

?>
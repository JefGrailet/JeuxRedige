<?php

/*
* Script to write an item in some list. The user must be logged and must be the author of the list.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './model/Emoticon.class.php';
require './model/GamesList.class.php';
require './model/Game.class.php';
require './model/ListItem.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Error where the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

$list = null;
$nbItems = 0;
if(!empty($_GET['id_list']) && preg_match('#^([0-9]+)$#', $_GET['id_list']))
{
   $listID = intval(Utils::secure($_GET['id_list']));
   $list = NULL;
   try
   {
      $list = new GamesList($listID);
      $nbItems = $list->countItems();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Liste introuvable');
   }
   
   // Forbidden access if the user's not the author
   if(!$list->isMine())
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cette liste n\'est pas la vôtre');
   }
}

// Webpage settings
WebpageHandler::addCSS('preview');
WebpageHandler::addJS('formatting');
WebpageHandler::addJS('preview');
WebpageHandler::addJS('games'); // For game selection

// Dialogs and JS stuff
$dialogs = '';
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs .= $formattingDialogsTpl;

// Content of the main form
$formData = array('listURL' => PathHandler::listURL($list->getAll()), 
'listTitle' => $list->get('title'), 
'listID' => $list->get('id_commentable'), 
'errors' => '', 
'displayedGame' => '', 
'title' => '',
'comment' => '', 
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
   $formData['title'] = Utils::secure($_POST['title']); // N.B.: unlike in other scripts, it can be empty here
   $formData['comment'] = Utils::secure($_POST['message']);
   $formData['game'] = Utils::secure($_POST['game']);
   
   $validGame = false;
   $selectedGame = null;
   $duplicate = false;
   try
   {
      $selectedGame = new Game($formData['game']);
      $validGame = true;
      $duplicate = $list->hasListed($formData['game']);
   }
   catch(Exception $e) { }
   
   // Various possible errors
   if(strlen($formData['game']) == 0 || strlen($formData['comment']) == 0)
      $formData['errors'] .= 'emptyFields|';
   if(strlen($formData['title']) > 60)
      $formData['errors'] .= 'titleTooLong|';
   if(strlen($formData['game']) > 0 && !$validGame)
      $formData['errors'] .= 'invalidGame|';
   if($duplicate)
   {
      $formData['errors'] .= 'duplicateGame|';
      
      // Form must also be reset regarding the game
      $formData['displayedGame'] = 'pick';
      $formData['game'] = '';
   }
   
   if(strlen($formData['errors']) == 0)
   {
      $newItem = null;
      try
      {
         $newItem = ListItem::insert($list->get('id_commentable'), 
                                     $selectedGame->get('tag'), 
                                     FormParsing::parse(Emoticon::parseEmoticonsShortcuts($formData['comment'])), 
                                     $nbItems + 1, 
                                     $formData['title']);
      }
      catch(Exception $e)
      {
         // Completing some fields
         if(strlen($formData['game']) > 0 && $validGame)
            $formData['displayedGame'] = 'chosen||'.$formData['game'].'|'.PathHandler::gameURL($selectedGame->getAll());
         else
            $formData['displayedGame'] = 'pick';
         
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewListItem.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Ajouter un item à la liste "'.$list->get('title').'"', $dialogs);
      }
      
      // Redirection
      $listURL = PathHandler::listURL($list->getAll());
      header('Location:'.$listURL.'#item'.($nbItems + 1));
      
      // Success page
      $tplInput = array('target' => $newPieceURL);
      $successPage = TemplateEngine::parse('view/content/NewContent.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Nouvel item pour la liste "'.$list->get('title').'"');
   }
   else
   {
      // Completing some fields
      if(strlen($formData['game']) > 0 && $validGame)
         $formData['displayedGame'] = 'chosen||'.$formData['game'].'|'.PathHandler::gameURL($selectedGame->getAll());
      else
         $formData['displayedGame'] = 'pick';
      
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewListItem.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Ajouter un item à la liste "'.$list->get('title').'"', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/NewListItem.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Ajouter un item à la liste "'.$list->get('title').'"', $dialogs);
}

?>
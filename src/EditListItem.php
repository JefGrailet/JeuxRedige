<?php

/*
* Script to edit an item list. As long as the current user is the author of the list, (s)he can 
* edit it.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './model/Emoticon.class.php';
require './model/GamesList.class.php';
require './model/ListItem.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Error if the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Obtains item ID and retrieves the corresponding entry
$item = NULL;
$parentList = NULL;
if(!empty($_GET['id_item']) && preg_match('#^([0-9]+)$#', $_GET['id_item']))
{
   $itemID = intval(Utils::secure($_GET['id_item']));
   try
   {
      $item = new ListItem($itemID);
      $parentList = new GamesList($item->get('id_commentable'));
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Elément de liste introuvable');
   }
   
   // Forbidden access if the user's neither the author, neither an admin
   if(!$parentList->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cette liste n\'est pas le vôtre');
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

// Dialogs and JS stuff
$dialogs = '';
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs .= $formattingDialogsTpl;

// Webpage settings
WebpageHandler::addCSS('preview');
WebpageHandler::addJS('formatting');
WebpageHandler::addJS('preview');

// Content of the main form
$formData = array('listURL' => PathHandler::listURL($parentList->getAll()), 
'listTitle' => $parentList->get('title'), 
'gameURL' => PathHandler::gameURL(array('tag' => $item->get('game'))), // "Cheating" the PathHandler::gameURL() function
'game' => $item->get('game'), 
'success' => '', 
'errors' => '', 
'itemID' => $item->get('id_item'), 
'title' => $item->get('subtitle'),
'comment' => FormParsing::unparse($item->get('comment')));

// Form treatment is similar to that of NewListItem.php
if(!empty($_POST['sent']))
{
   $formData['title'] = Utils::secure($_POST['title']);
   $formData['comment'] = Utils::secure($_POST['message']);
   
   // Errors (missing title, title too long or bad policy)
   if(strlen($formData['comment']) == 0)
      $formData['errors'] .= 'emptyFields|';
   else if(strlen($formData['title']) > 60)
      $formData['errors'] .= 'titleTooLong|';
   
   if(strlen($formData['errors']) == 0)
   {
      Database::beginTransaction();
      try
      {
         $item->edit(FormParsing::parse(Emoticon::parseEmoticonsShortcuts($formData['comment'])), $formData['title']);
         $parentList->update(); // Will just update last edition date
         
         Database::commit();
      }
      catch(Exception $e)
      {
         Database::rollback();
         
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/EditListItem.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier ma liste "'.$parentList->get('title').'"', $dialogs);
      }
      
      // Reloads page and notifies the user everything was updated
      $formData['success'] = 'yes';
      $formTpl = TemplateEngine::parse('view/content/EditListItem.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Modifier l\'élément n°'.$item->get('rank').' de ma liste "'.$parentList->get('title').'"', $dialogs);
   }
   else
   {
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/EditListItem.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Modifier l\'élément n°'.$item->get('rank').' de ma liste "'.$parentList->get('title').'"', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/EditListItem.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Modifier l\'élément n°'.$item->get('rank').' de ma liste "'.$parentList->get('title').'"', $dialogs);
}

?>

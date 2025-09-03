<?php

/*
* Script to edit a piece of trivia. As long as the current user is the author of the piece, (s)he 
* can edit it.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './model/Game.class.php';
require './model/Trivia.class.php';

WebpageHandler::redirectionAtLoggingIn();

// Error if the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Obtains piece ID and retrieves the corresponding entry
if(!empty($_GET['id_trivia']) && preg_match('#^([0-9]+)$#', $_GET['id_trivia']))
{
   $triviaID = intval(Utils::secure($_GET['id_trivia']));
   $trivia = NULL;
   $game = NULL;
   try
   {
      $trivia = new Trivia($triviaID);
      $game = new Game($trivia->get('game'));
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Anecdote introuvable');
   }
   
   // Forbidden access if the user's neither the author, neither an admin
   if(!$trivia->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cette anecdote n\'est pas la vôtre');
   }
   
   $dialogs = '';
   $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
   if(!TemplateEngine::hasFailed($formattingDialogsTpl))
      $dialogs .= $formattingDialogsTpl;

   // Webpage settings
   WebpageHandler::addCSS('preview');
   WebpageHandler::addJS('formatting');
   WebpageHandler::addJS('preview');
   
   // Edition form components (with current values)
   $formData = array('URL' => PathHandler::triviaURL($trivia->getAll()),
   'gameURL' => PathHandler::gameURL($game->getAll()), 
   'game' => $trivia->get('game'), 
   'success' => '', 
   'errors' => '', 
   'ID' => $trivia->get('id_commentable'), 
   'title' => $trivia->get('title'),
   'content' => FormParsing::unparse($trivia->get('content')));
   
   // Form treatment is similar to that of NewTrivia.php
   if(!empty($_POST['sent']))
   {
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['content'] = Utils::secure($_POST['message']);
      
      // Various errors (title already used for alias, wrong genre, etc.)
      if(strlen($formData['title']) == 0 || strlen($formData['content']) == 0)
         $formData['errors'] .= 'emptyFields|';
      if(strlen($formData['title']) > 60)
         $formData['errors'] .= 'titleTooLong|';
      
      if(strlen($formData['errors']) == 0)
      {
         try
         {
            $trivia->edit($formData['title'], FormParsing::parse($formData['content']));
         }
         catch(Exception $e)
         {
            $formData['errors'] = 'dbError';
            $formTpl = TemplateEngine::parse('view/content/EditTrivia.form.ctpl', $formData);
            WebpageHandler::wrap($formTpl, 'Modifier mon anecdote à propos de '.$trivia->get('game'), $dialogs);
         }
         
         // Reloads page and notifies the user everything was updated
         $formData['success'] = 'yes';
         $formTpl = TemplateEngine::parse('view/content/EditTrivia.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier mon anecdote à propos de '.$trivia->get('game'), $dialogs);
      }
      else
      {
         $formData['errors'] = substr($formData['errors'], 0, -1);
         $formTpl = TemplateEngine::parse('view/content/EditTrivia.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier mon anecdote à propos de '.$trivia->get('game'), $dialogs);
      }
   }
   else
   {
      $formTpl = TemplateEngine::parse('view/content/EditTrivia.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Modifier mon anecdote à propos de '.$trivia->get('game'), $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>

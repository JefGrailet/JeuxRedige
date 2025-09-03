<?php

/*
* Script to edit the main settings of a list. As long as the current user is the creator, (s)he 
* can edit it.
*/

require './libraries/Header.lib.php';
require './model/GamesList.class.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::addJS('uploads'); // Custom thumbnail creation by default, for now
WebpageHandler::addJS('javascript/list_editor');

// Error if the user is not logged in
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Obtains list ID and retrieves the corresponding entry
if(!empty($_GET['id_list']) && preg_match('#^([0-9]+)$#', $_GET['id_list']))
{
   $listID = intval(Utils::secure($_GET['id_list']));
   $list = NULL;
   try
   {
      $list = new GamesList($listID);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Liste introuvable');
   }
   
   // Forbidden access if the user's neither the author, neither an admin
   if(!$list->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
   {
      $tplInput = array('error' => 'notYours');
      $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Cette liste n\'est pas la vôtre');
   }
   
   // Dialog for thumbnail creation
   $dialogs = '';
   $thumbnailDialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
   if(!TemplateEngine::hasFailed($thumbnailDialogTpl))
      $dialogs .= $thumbnailDialogTpl;
   
   // Ordering policies
   $orderingPolicies = array('default', 'top');
   $formPolicies = 'default,Affichage par défaut|top,Classement';
   
   // Array which serves both for template input and collecting $_POST values
   $formData = array('URL' => PathHandler::listURL($list->getAll()), 
   'success' => '', 
   'errors' => '', 
   'listID' => $listID, 
   'title' => $list->get('title'), 
   'thumbnailPath' => PathHandler::HTTP_PATH().'upload/commentables/'.$listID.'.jpg', 
   'description' => $list->get('description'), 
   'ordering' => $list->get('ordering').'||'.$formPolicies, 
   'thumbnail' => 'none');
   
   // Form treatment is similar to that of NewTrivia.php
   if(!empty($_POST['sent']))
   {
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['description'] = Utils::secure($_POST['description']);
      $pickedOrdering = Utils::secure($_POST['ordering']);
      $formData['ordering'] = $pickedOrdering.'||'.$formPolicies;
      
      $formData['thumbnail'] = Utils::secure($_POST['thumbnail']);
      if($formData['thumbnail'] !== 'none' && file_exists(PathHandler::WWW_PATH().substr($formData['thumbnail'], 2)))
         $formData['thumbnailPath'] = $formData['thumbnail'];
      
      // Errors (missing title, title too long or bad policy)
      if(strlen($formData['title']) == 0 || strlen($formData['description']) == 0)
         $formData['errors'] .= 'emptyFields|';
      else if(strlen($formData['title']) > 60 || strlen($formData['description']) > 1000)
         $formData['errors'] .= 'dataTooLong|';
      if(!in_array($pickedOrdering, $orderingPolicies))
         $formData['errors'] .= 'badOrdering|';
      
      if(strlen($formData['errors']) == 0)
      {
         try
         {
            $list->edit($formData['title'], $formData['description'], $pickedOrdering);
         }
         catch(Exception $e)
         {
            $formData['errors'] = 'dbError';
            $formTpl = TemplateEngine::parse('view/content/EditList.form.ctpl', $formData);
            WebpageHandler::wrap($formTpl, 'Modifier ma liste "'.$list->get('title').'"', $dialogs);
         }
         
         // Saves the new thumbnail (if thumbnail changed)
         if($formData['thumbnail'] !== 'none' && $formData['thumbnail'] !== 'CUSTOM' && file_exists(PathHandler::WWW_PATH().substr($formData['thumbnail'], 2)))
         {
            require './libraries/Buffer.lib.php';
            
            $fileName = substr(strrchr($formData['thumbnail'], '/'), 1);
            Buffer::save('upload/commentables', $fileName, strval($list->get('id_commentable')));
            
            // Resets the parts of the form used for thumbnail edition
            $formData['thumbnailPath'] = PathHandler::HTTP_PATH().'upload/commentables/'.$listID.'.jpg';
            $formData['thumbnail'] = 'none';
         }
         
         // Reloads page and notifies the user everything was updated
         $formData['success'] = 'yes';
         $formTpl = TemplateEngine::parse('view/content/EditList.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier ma liste "'.$list->get('title').'"', $dialogs);
      }
      else
      {
         $formData['errors'] = substr($formData['errors'], 0, -1);
         $formTpl = TemplateEngine::parse('view/content/EditList.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Modifier ma liste "'.$list->get('title').'"', $dialogs);
      }
   }
   else
   {
      $formTpl = TemplateEngine::parse('view/content/EditList.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Modifier ma liste "'.$list->get('title').'"', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>

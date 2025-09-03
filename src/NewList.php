<?php

/**
* This script is designed to allow a logged in user to create a new list. This only consists in 
* giving a title, a thumbnail and an ordering policy.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::addJS('uploads'); // Custom thumbnail creation by default, for now
WebpageHandler::addJS('javascript/list_editor');

// Errors where the user is either not logged in either not allowed to create content
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$fullData['advanced_features']))
{
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditContent.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

require './model/GamesList.class.php';
require './libraries/Buffer.lib.php';

// Dialog for thumbnail creation
$dialogs = '';
$thumbnailDialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
if(!TemplateEngine::hasFailed($thumbnailDialogTpl))
   $dialogs .= $thumbnailDialogTpl;

// Thumbnail
$currentThumbnailPath = Buffer::getThumbnail();
$currentThumbnail = 'none';
if(strlen($currentThumbnailPath) == 0)
   $currentThumbnailPath = './defaultthumbnail.jpg';
else
   $currentThumbnail = './'.substr($currentThumbnailPath, strlen(PathHandler::HTTP_PATH()));

// Ordering policies
$orderingPolicies = array('default', 'top');
$formPolicies = 'default,Affichage par défaut|top,Classement';

// Array which serves both for template input and collecting $_POST values
$formData = array('errors' => '', 
'title' => '', 
'thumbnailPath' => $currentThumbnailPath, 
'description' => '', 
'ordering' => 'default||'.$formPolicies, 
'thumbnail' => $currentThumbnail);

if(!empty($_POST['sent']))
{
   $formData['title'] = Utils::secure($_POST['title']);
   $formData['thumbnail'] = Utils::secure($_POST['thumbnail']);
   $formData['description'] = Utils::secure($_POST['description']);
   $pickedOrdering = Utils::secure($_POST['ordering']);
   $formData['ordering'] = $pickedOrdering.'||'.$formPolicies;
   
   if($formData['thumbnail'] !== 'none' && file_exists(PathHandler::WWW_PATH().substr($formData['thumbnail'], 2)))
      $formData['thumbnailPath'] = $formData['thumbnail'];
   
   // Errors (missing title, title too long, missing thumbnail or bad policy)
   if(strlen($formData['title']) == 0 || strlen($formData['description']) == 0)
      $formData['errors'] .= 'emptyFields|';
   else if(strlen($formData['title']) > 60 || strlen($formData['description']) > 1000)
      $formData['errors'] .= 'dataTooLong|';
   if($formData['thumbnail'] === 'none')
      $formData['errors'] .= 'noThumbnail|';
   if(!in_array($pickedOrdering, $orderingPolicies))
      $formData['errors'] .= 'badOrdering|';
   
   if(strlen($formData['errors']) == 0)
   {
      $newList = NULL;
      try
      {
         $newList = GamesList::insert($formData['title'], $formData['description'], $pickedOrdering);
      }
      catch(Exception $e)
      {
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewList.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Créer une nouvelle liste', $dialogs);
      }
      
      // Saves the thumbnail
      $fileName = substr(strrchr($formData['thumbnail'], '/'), 1);
      Buffer::save('upload/commentables', $fileName, strval($newList->get('id_commentable')));
      
      // Redirection
      $newListURL = PathHandler::listURL($newList->getAll());
      // header('Location:'.$newListURL);
      
      // Success page
      $tplInput = array('target' => $newListURL);
      $successPage = TemplateEngine::parse('view/content/NewContent.success.ctpl', $tplInput);
      WebpageHandler::wrap($successPage, 'Créer une nouvelle liste');
   }
   else
   {
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewList.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Créer une nouvelle liste', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/NewList.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Créer une nouvelle liste', $dialogs);
}
   
?>

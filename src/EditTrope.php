<?php

/*
* Script to edit a trope. Anyone authorized can modify an existing trope.
*/

require './libraries/Header.lib.php';
require './libraries/Buffer.lib.php';
require './model/Tag.class.php';
require './model/Trope.class.php';

WebpageHandler::redirectionAtLoggingIn();

// The page can only be consulted by logged users
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditTrope.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$fullData['advanced_features']))
{
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditTrope.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}
/*
TODO (later)
if(!Utils::check(LoggedUser::$data['can_edit_games']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditTrope.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}
*/

// Icon creation dialog
$dialogTpl = TemplateEngine::parse('view/dialog/CustomIcon.dialog.ctpl');
$dialogs = '';
if(!TemplateEngine::hasFailed($dialogTpl))
   $dialogs = $dialogTpl;

// Obtains trope name and retrieves the corresponding entry
if(!empty($_GET['trope']))
{
   $tropeName = Utils::secure(urldecode($_GET['trope']));
   $trope = null;
   try
   {
      $trope = new Trope($tropeName);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingTrope';
      $tpl = TemplateEngine::parse('view/content/EditTrope.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Code introuvable');
   }
   
   // Webpage settings
   WebpageHandler::addJS('trope_editor');
   
   // Edition form components (with current values)
   $formData = array('target' => $tropeName, 
   'success' => '', 
   'errors' => '',
   'tag' => $trope->get('tag'), 
   'icon' => '', 
   'description' => $trope->get('description'), 
   'color' => $trope->get('color'));
   
   // Trope icon
   $currentIcon = Buffer::getTropeIcon();
   $iconExists = false;
   $tropeIconName = PathHandler::formatForURL($trope->get('tag')).'.png';
   if(file_exists(PathHandler::WWW_PATH().'upload/tropes/'.$tropeIconName))
   {
      $iconExists = true;
      $formData['icon'] = './upload/tropes/'.$tropeIconName;
   }
   else if(strlen($currentIcon) > 0)
      $formData['icon'] = './'.substr($currentIcon, strlen(PathHandler::HTTP_PATH()));
   else
      $formData['icon'] = './default_trope_icon.png';
   
   // Form treatment is similar to that of NewTrope.php
   if(!empty($_POST['sent']))
   {
      $formData['description'] = Utils::secure($_POST['description']);
      $formData['color'] = Utils::secure($_POST['color']);
      $formData['icon'] = Utils::secure($_POST['icon']);
      
      // Various errors (title already used for alias, description length, etc.)
      if(strlen($formData['description']) == 0)
         $formData['errors'] .= 'emptyFields|';
      if(strlen($formData['description']) > 250)
         $formData['errors'] .= 'dataTooLong|';
      if($formData['icon'] === './default_trope_icon.png' || !file_exists(PathHandler::WWW_PATH().substr($formData['icon'], 2)))
         $formData['errors'] .= 'invalidIcon|';
      if(!preg_match('!^#([a-fA-F0-9]{6})$!', $formData['color']))
         $formData['errors'] .= 'invalidColor|';
      
      if(strlen($formData['errors']) == 0)
      {
         // Updates the trope
         try
         {
            $trope->update($formData['color'], $formData['description']);
         }
         catch(Exception $e)
         {
            $formData['errors'] = 'dbError';
            $finalTpl = TemplateEngine::parse('view/content/EditTrope.form.ctpl', $formData);
            WebpageHandler::wrap($finalTpl, 'Editer le code vidéoludique "'.$trope->get('tag').'"', $dialogs);
         }
         
         // Updates the icon if edited
         if($formData['icon'] !== $currentIcon || (!$iconExists && $formData['icon'] !== './default_trope_icon.png'))
         {
            $fileName = substr(strrchr($formData['icon'], '/'), 1);
            Buffer::save('upload/tropes', $fileName, PathHandler::formatForURL($trope->get('tag')));
            $formData['icon'] = './upload/tropes/'.$tropeIconName;
         }
         
         // Reloads page and notifies the user everything was updated
         $formData['success'] = 'yes';
         $finalTpl = TemplateEngine::parse('view/content/EditTrope.form.ctpl', $formData);
         WebpageHandler::wrap($finalTpl, 'Editer le code vidéoludique "'.$trope->get('tag').'"', $dialogs);
      }
      else
      {
         $formData['errors'] = substr($formData['errors'], 0, -1);
         $finalTpl = TemplateEngine::parse('view/content/EditTrope.form.ctpl', $formData);
         WebpageHandler::wrap($finalTpl, 'Editer le code vidéoludique "'.$trope->get('tag').'"', $dialogs);
      }
   }
   else
   {
      $finalTpl = TemplateEngine::parse('view/content/EditTrope.form.ctpl', $formData);
      WebpageHandler::wrap($finalTpl, 'Editer le code vidéoludique "'.$trope->get('tag').'"', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingTrope');
   $tpl = TemplateEngine::parse('view/content/EditTrope.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>

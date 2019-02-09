<?php

/*
* Script to create a new trope in the DB. Exclusive to authorized users.
*/

require './libraries/Header.lib.php';
require './libraries/Buffer.lib.php';
require './model/Tag.class.php';
require './model/Trope.class.php';

WebpageHandler::redirectionAtLoggingIn();

// The page can only be consulted by logged users with advanced features
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

// Webpage settings
WebpageHandler::addJS('trope_editor');

// Icon creation dialog
$dialogTpl = TemplateEngine::parse('view/dialog/CustomIcon.dialog.ctpl');
$dialogs = '';
if(!TemplateEngine::hasFailed($dialogTpl))
   $dialogs = $dialogTpl;

// Trope icon details (default image or buffered image)
$currentTropeIcon = Buffer::getTropeIcon();
$currentIconValue = '';
if(strlen($currentTropeIcon) == 0)
   $currentTropeIcon = './default_trope_icon.png';
else
   $currentIconValue = './'.substr($currentTropeIcon, strlen(PathHandler::HTTP_PATH));

// Form input
$formData = array('errors' => '',
'tag' => '',
'iconPath' => $currentTropeIcon, 
'description' => '', 
'color' => '#C0C0C0', 
'icon' => $currentIconValue);

// Form treatment starts here
if(!empty($_POST['sent']))
{
   $formData['tag'] = Utils::secure($_POST['tag']);
   $formData['description'] = Utils::secure($_POST['description']);
   $formData['color'] = Utils::secure($_POST['color']);
   $formData['icon'] = Utils::secure($_POST['icon']);
   
   // Checks the title of the trope is not used for a game
   $titleOK = false;
   if(strlen($formData['tag']) > 0)
   {
      try
      {
         $titleTag = new Tag($formData['tag']);
         if($titleTag->countAliases() == 0 && $titleTag->canBeAnAlias())
            $titleOK = true;
      }
      catch(Exception $e)
      {
         $formData['errors'] .= 'dbError|';
      }
   }
   
   // Various errors (title already used for alias, description length, etc.)
   if(strlen($formData['tag']) == 0 || strlen($formData['description']) == 0)
      $formData['errors'] .= 'emptyFields|';
   if(strlen($formData['tag']) > 50 || strlen($formData['description']) > 250)
      $formData['errors'] .= 'dataTooLong|';
   if(!$titleOK && strlen($formData['tag']) > 0)
      $formData['errors'] .= 'invalidTitle|';
   if($formData['icon'] === './default_trope_icon.png' || !file_exists(PathHandler::WWW_PATH.substr($formData['icon'], 2)))
      $formData['errors'] .= 'invalidIcon|';
   if(!preg_match('!^#([a-fA-F0-9]{6})$!', $formData['color']))
      $formData['errors'] .= 'invalidColor|';
   
   if(strlen($formData['errors']) == 0)
   {
      // Inserts the trope (new error display in case of DB problem)
      try
      {
         $newTrope = Trope::insert($formData['tag'], $formData['color'], $formData['description']);
      }
      catch(Exception $e)
      {
         echo $e->getMessage();
         $formData['errors'] = 'dbError';
         $formTpl = TemplateEngine::parse('view/content/NewTrope.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Ajouter un code vidéoludique dans la base de données', $dialogs);
      }
      
      $fileName = substr(strrchr($formData['icon'], '/'), 1);
      Buffer::save('upload/tropes', $fileName, PathHandler::formatForURL($formData['tag']));
      
      // Success page
      header('Location:'.PathHandler::HTTP_PATH.'Tropes.php');
      $tplInput = array('title' => $newTrope->get('tag'));
      $successPage = TemplateEngine::parse('view/content/NewTrope.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Ajouter un code vidéoludique dans la base de données');
   }
   else
   {
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $formTpl = TemplateEngine::parse('view/content/NewTrope.form.ctpl', $formData);
      WebpageHandler::wrap($formTpl, 'Ajouter un code vidéoludique dans la base de données', $dialogs);
   }
}
else
{
   $formTpl = TemplateEngine::parse('view/content/NewTrope.form.ctpl', $formData);
   WebpageHandler::wrap($formTpl, 'Ajouter un code vidéoludique dans la base de données', $dialogs);
}

?>

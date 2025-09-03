<?php

/**
* This scripts performs the delete operation on a given topic. It is visited via a dialog
* box while checking the topic (only visible if the user is logged and has the authorization
* for this operation), and redirects to the index upon completion. Various errors can happen,
* though.
*/

require './libraries/Header.lib.php';
require './model/Topic.class.php';

// Errors where the user is either not logged in, either not allowed to lock/unlock
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/QuickModeration.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$data['can_lock']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'opForbidden');
   $tpl = TemplateEngine::parse('view/content/QuickModeration.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);
   
   // Gets the topic and deletes it just after
   try
   {
      $topic = new Topic($getID);
      $topic->delete();
      
      // Redirects and displays some success page if redirection fails
      header('Location:./Forum.php');
      $tplInput = array('opType' => 'delete', 'target' => './Forum.php');
      $tpl = TemplateEngine::parse('view/content/QuickModeration.success.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet modéré avec succès');
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';

      $tpl = TemplateEngine::parse('view/content/QuickModeration.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet introuvable');
   }
}
else
{
   header('Location:./Forum.php');
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/QuickModeration.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Sujet introuvable');
}

?>

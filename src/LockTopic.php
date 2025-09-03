<?php

/**
* This scripts performs the lock/unlock operation on a given topic. It is visited via a dialog
* box while checking the topic (only visible if the user is logged and has the authorization
* for this operation), and redirects to that topic upon completion. Various errors can happen,
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
   
   // Gets the topic, checks if user can lock/unlock and performs the operation
   try
   {
      $topic = new Topic($getID);
      
      $topicURL = PathHandler::topicURL($topic->getAll());

      // Lock/unlocks depending on the status of the topic; prepare the template input
      $lockedOrNot = Utils::check($topic->get('is_locked'));
      $tplInput = array('opType' => 'lock', 'target' => $topicURL);
      if($lockedOrNot)
      {
         $topic->unlock();
         $tplInput['opType'] = 'unlock';
      }
      else
         $topic->lock();
         
      // Redirects and displays some success page if redirection fails
      header('Location:'.$topicURL);
      $tpl = TemplateEngine::parse('view/content/QuickModeration.success.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet modéré avec succès');
   }
   catch(Exception $e)
   {
      header('Location:./index.php');
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';

      $tpl = TemplateEngine::parse('view/content/QuickModeration.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet introuvable');
   }
}
else
{
   header('Location:./index.php');
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/QuickModeration.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Sujet introuvable');
}

?>

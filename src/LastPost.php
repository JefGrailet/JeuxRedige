<?php

/**
* This script redirects one to the last message of a topic. The only input is the ID of that topic, 
* and this script is responsible for counting the amount of messages in the related topic which 
* appears before this one and producing the right URL (i.e., with the right page and index). It 
* also takes account of the settings of the current user.
*/

require './libraries/Header.lib.php';

require './model/Topic.class.php';

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);

   // Get the related topic and deals with possible errors.
   $nbPosts = 0;
   try
   {
      $topic = new Topic($getID);
      $nbPosts = $topic->countPosts();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingTopic';
      $tpl = TemplateEngine::parse('view/content/LastPost.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   // Computes the link to the context of the message
   $postPage = ceil($nbPosts / WebpageHandler::$miscParams['posts_per_page']);
   $postURL = PathHandler::topicURL($topic->getAll(), $postPage).'#'.$nbPosts;
   
   // Redirection + default page in case the redirection fails
   header('Location:'.$postURL);
   
   $finalTplInput = array('topicTitle' => $topic->get('title'), 'URL' => $postURL);
   $finalTpl = TemplateEngine::parse('view/content/LastPost.ctpl', $finalTplInput);
   WebpageHandler::wrap($finalTpl, 'Redirection vers le dernier message du sujet "'.$topic->get('title').'"');
}
else
{
   $tplInput = array('error' => 'missingTopic');
   $tpl = TemplateEngine::parse('view/content/LastPost.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le sujet n\'est pas précisé');
}
?>

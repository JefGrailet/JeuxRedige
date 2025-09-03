<?php

/**
* This script redirects one to a message in its context. The only input is the ID of that message, 
* and this script is responsible for counting the amount of messages in the related topic which 
* appears before this one and producing the right URL (i.e., with the right page and index).
*/

require './libraries/Header.lib.php';

require './model/Topic.class.php';
require './model/Post.class.php';

if(!empty($_GET['id_post']) && preg_match('#^([0-9]+)$#', $_GET['id_post']))
{
   $getID = intval($_GET['id_post']);
   
   // Gets the post, its data and deals with errors if any.
   $post = NULL;
   try
   {
      $post = new Post($getID);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingPost';
      $tpl = TemplateEngine::parse('view/content/Context.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   // Same applies with the related topic, with some other errors.
   $nbPostsBefore = 0;
   try
   {
      $topic = new Topic($post->get('id_topic'));
      $nbPostsBefore = $topic->countPosts($post->get('date'), true);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';
      $tpl = TemplateEngine::parse('view/content/Context.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   // Computes the link to the context of the message
   $postPage = ceil(($nbPostsBefore + 1) / WebpageHandler::$miscParams['posts_per_page']);
   $postURL = PathHandler::topicURL($topic->getAll(), $postPage).'#'.($nbPostsBefore + 1);
   
   // Redirection + default page in case the redirection fails
   header('Location:'.$postURL);
   
   $finalTplInput = array('URL' => $postURL);
   $finalTpl = TemplateEngine::parse('view/content/Context.ctpl', $finalTplInput);
   WebpageHandler::wrap($finalTpl, 'Redirection vers le contexte du message');
}
else
{
   $tplInput = array('error' => 'missingPost');
   $tpl = TemplateEngine::parse('view/content/Context.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le message est manquant');
}
?>

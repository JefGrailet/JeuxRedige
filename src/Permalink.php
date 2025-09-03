<?php

/**
* This script shows a single message out of its context. The only input is the ID of that message.
*/

require './libraries/Header.lib.php';

require './model/Topic.class.php';
require './model/Post.class.php';
require './model/User.class.php'; // For online status
require './view/intermediate/Post.ir.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['id_post']) && preg_match('#^([0-9]+)$#', $_GET['id_post']))
{
   $getID = intval($_GET['id_post']);
   
   // Gets the post, its data and deals with errors if any.
   $post = NULL;
   try
   {
      $post = new Post($getID);
      $post->getUserInteraction();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingPost';
      $tpl = TemplateEngine::parse('view/content/Permalink.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   $postArr = $post->getAll();
   
   // Checks online status of pseudonyms in "author" field of the retrieved post
   $listedUsers = array();
   $listedAdmins = array();
   if($postArr['posted_as'] === 'regular user')
      array_push($listedUsers, $postArr['author']);
   else if($postArr['posted_as'] === 'administrator')
      array_push($listedAdmins, $postArr['author']);
   
   $online = null;
   try
   {
      $online = User::checkOnlineStatus($listedUsers, $listedAdmins);
   }
   catch(Exception $e) {}
   
   // Formats the post
   if($postArr['posted_as'] !== 'anonymous')
   {
      if($online != null && in_array($postArr['author'], $online))
         $postArr['online'] = true;
      $postArr['content'] = MessageParsing::parse($postArr['content']);
      $postArr['content'] = MessageParsing::removeReferences($postArr['content']);
   }
   $intermediate = PostIR::process($postArr, 0);
   $intermediate['permalink'] = ''; // Not needed here
   
   // Webpage settings
   WebpageHandler::addCSS('topic');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('topic_medium');
   WebpageHandler::addJS('topic_interaction');
   WebpageHandler::addJS('post_interaction');
   WebpageHandler::noContainer();
   
   // Dialogs for interactions (showing them, sending an alert, etc.)
   $dialogs = '';
   $interactionsTpl = TemplateEngine::parse('view/dialog/Interactions.multiple.ctpl');
   if(!TemplateEngine::hasFailed($interactionsTpl))
      $dialogs .= $interactionsTpl;
   
   // Generates the page
   $finalPost = TemplateEngine::parse('view/content/Post.ctpl', $intermediate);
   $finalTplInput = array('idMsg' => $post->get('id_post'), 'msg' => $finalPost);
   $finalTpl = TemplateEngine::parse('view/content/Permalink.ctpl', $finalTplInput);
   WebpageHandler::wrap($finalTpl, 'Message #'.$post->get('id_post'), $dialogs);
}
else
{
   $tplInput = array('error' => 'missingPost');
   $tpl = TemplateEngine::parse('view/content/Permalink.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le message est manquant');
}
?>

<?php

/**
* This script shows the history of a given post. It starts by displaying the current version, then 
* displays the previous versions starting with the earliest one.
*/

require './libraries/Header.lib.php';

require './model/Topic.class.php';
require './model/Post.class.php';
require './libraries/MessageParsing.lib.php';
require './view/intermediate/Post.ir.php';
require './view/intermediate/PostHistory.ir.php';

WebpageHandler::redirectionAtLoggingIn();

$finalTplInput = array('idMsg' => 0, 
'currentMsg' => '', 
'previousVersions' => '');

if(!empty($_GET['id_post']) && preg_match('#^([0-9]+)$#', $_GET['id_post']))
{
   $getID = intval($_GET['id_post']);
   
   // Gets the post, its data and deals with errors if any.
   $post = NULL;
   $versions = NULL;
   try
   {
      $post = new Post($getID);
      $versions = $post->getPreviousVersions();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingPost';
      $tpl = TemplateEngine::parse('view/content/PostHistory.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   $finalTplInput['idMsg'] = $post->get('id_post');
   $postArr = $post->getAll();

   // Renders the current message
   $postArr['content'] = MessageParsing::parse($postArr['content']);
   $postArr['content'] = MessageParsing::removeReferences($postArr['content']);
   
   // Data about history is nullified (since we will display former versions)
   $postArr['nb_edits'] = 0;
   $postArr['last_edit'] = '1970-01-01 00:00:00';
   $postArr['last_editor'] = '';
   
   $postIR = PostIR::process($postArr, 0, false);
   $postTpl = TemplateEngine::parse('view/content/Post.ctpl', $postIR);
   if(!TemplateEngine::hasFailed($postTpl))
      $finalTplInput['currentMsg'] .= $postTpl;
   else
      WebpageHandler::wrap($postTpl, 'Une erreur est survenue lors de la lecture du message');

   // Renders previous versions
   if($versions == NULL)
   {
      $finalTplInput['previousVersions'] = TemplateEngine::parse('view/content/PostHistory.empty.ctpl');
   }
   else
   {
      $fullInput = array();
      for($i = 0; $i < count($versions); $i++)
      {
         if(Utils::check($versions[$i]['censorship']))
            $versions[$i]['content'] = MessageParsing::parseCensored($versions[$i]['content']);
         else
            $versions[$i]['content'] = MessageParsing::parse($versions[$i]['content'], ($i + 1));
         $versions[$i]['content'] = MessageParsing::removeReferences($versions[$i]['content']);
         $versions[$i]['content'] = $versions[$i]['content'];
      
         $versionIR = PostHistoryIR::process($versions[$i]);
         array_push($fullInput, $versionIR);
      }
      
      $versionsTpl = TemplateEngine::parseMultiple('view/content/ArchivedPost.ctpl', $fullInput);
      if(!TemplateEngine::hasFailed($versionsTpl))
      {
         for($i = 0; $i < count($versionsTpl); $i++)
            $finalTplInput['previousVersions'] .= $versionsTpl[$i];
      }
      else
         WebpageHandler::wrap($versionsTpl, 'Une erreur est survenue lors de la lecture des messages');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('topic');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('topic_medium');
   WebpageHandler::addJS('topic_interaction');
   WebpageHandler::addJS('jquery.visible');
   WebpageHandler::addJS('pages');
   WebpageHandler::addJS('post_censorship');
   WebpageHandler::noContainer();
   
   // Dialogs for interactions (showing them, sending an alert, etc.)
   $dialogs = '';
   $interactionsTpl = TemplateEngine::parse('view/dialog/Interactions.multiple.ctpl');
   if(!TemplateEngine::hasFailed($interactionsTpl))
      $dialogs .= $interactionsTpl;
   
   // Final display
   $finalTpl = TemplateEngine::parse('view/content/PostHistory.ctpl', $finalTplInput);
   WebpageHandler::wrap($finalTpl, 'Historique du message #'.$post->get('id_post'), $dialogs);
}
else
{
   $tplInput = array('error' => 'missingPost');
   $tpl = TemplateEngine::parse('view/content/PostHistory.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le message est manquant');
}
?>

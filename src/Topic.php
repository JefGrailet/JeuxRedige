<?php

/**
* This script displays a topic (header, messages, list of pages). It requires several intermediate
* functions in order to "translate" the objects into arrays that can be used by the templates.
*/

require './libraries/Header.lib.php';

require './view/intermediate/TopicHeader.ir.php';
require './view/intermediate/Post.ir.php';
require './model/Topic.class.php';
require './model/Post.class.php';
require './model/User.class.php';
require './libraries/Anonymous.lib.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);
   
   $dialogs = '';
   
   // Prepares the input for the topic template
   $finalTplInput = array('header' => '', 
   'pagesConfig' => '', 
   'replyLink' => '', 
   'posts' => '', 
   'replyForm' => '');

   // Obtains topic, related data and posts
   $posts = null;
   $anonymousPosting = true;
   try
   {
      $topic = new Topic($getID);
      $topic->loadMetadata();
      $nbPosts = $topic->countPosts();
      
      // Anonymous posting can be deactivated if desired by the author of the topic
      if(!Utils::check($topic->get('is_anon_posting_enabled')))
         $anonymousPosting = false;
      
      // Gets current page and computes the first index to retrieve the messages (or posts)
      $currentPage = 1;
      $nbPages = ceil($nbPosts / WebpageHandler::$miscParams['posts_per_page']);
      $firstPost = 0;
      if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
      {
         $getPage = intval($_GET['page']);
         if($getPage <= $nbPages)
         {
            $currentPage = $getPage;
            $firstPost = ($getPage - 1) * WebpageHandler::$miscParams['posts_per_page'];
         }
      }
      
      // Deals with user's view (if logged in)
      if(LoggedUser::isLoggedIn())
      {
         // Creates a view for this user if it doesn't exist yet.
         if($topic->getBufferedView() == NULL)
            $topic->createView();
         
         /*
          * Updates the count of seen messages; note that nothing (i.e. no SQL request) happens if 
          * the total of messages covered up to this page is below the amount of covered messages 
          * in the user's view.
          */
         
         $seenMessages = $currentPage * WebpageHandler::$miscParams['posts_per_page'];
         if($seenMessages > $nbPosts)
            $seenMessages = $nbPosts;
         $topic->updateLastSeen($seenMessages);
      }
      
      $finalTplInput['pagesConfig'] = WebpageHandler::$miscParams['posts_per_page'].'|'.$nbPosts.'|'.$currentPage;
      $finalTplInput['pagesConfig'] .= '|'.PathHandler::topicURL($topic->getAll(), '[]');
      $finalTplInput['pagesConfig'] .= '|GetPosts.php?id_topic='.$topic->get('id_topic');
      $finalTplInput['pagesConfig'] .= '|RefreshTopic.php?id_topic='.$topic->get('id_topic');
      $finalTplInput['pagesConfig'] .= '|CheckTopic.php?id_topic='.$topic->get('id_topic');
      
      if((LoggedUser::isLoggedIn()) || Utils::check($topic->get('is_anon_posting_enabled')))
         $finalTplInput['replyLink'] = 'yes||./PostMessage.php?id_topic='.$topic->get('id_topic');
      $posts = $topic->getPosts($firstPost, WebpageHandler::$miscParams['posts_per_page']);
      Post::getUserInteractions($posts);
   }
   // Handles exceptions
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';
      $tpl = TemplateEngine::parse('view/content/Topic.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet introuvable');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('topic');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('topic_medium');
   WebpageHandler::addCSS('topic_header');
   if($topic->hasGames())
      WebpageHandler::addCSS('media');
   WebpageHandler::addJS('topic_interaction');
   WebpageHandler::addJS('post_interaction');
   WebpageHandler::addJS('jquery.visible');
   WebpageHandler::addJS('pages');
   WebpageHandler::addJS('refresh');
   WebpageHandler::changeContainer('topicContent');
   
   // Dialog boxes for the moderator (lock, unlock and delete)
   $dialogs = '';
   if(LoggedUser::isLoggedIn())
   {
      if(Utils::check(LoggedUser::$data['can_lock']))
      {
         $tplInput = array('topicID' => $getID, 'lockStatus' => 'unlocked');
         if(Utils::check($topic->get('is_locked')))
            $tplInput['lockStatus'] = 'locked';
         $dialogTpl = TemplateEngine::parse('view/dialog/LockTopic.dialog.ctpl', $tplInput);
         if(!TemplateEngine::hasFailed($dialogTpl))
            $dialogs .= $dialogTpl;
      }
      if(Utils::check(LoggedUser::$data['can_delete']))
      {
         $dialogTpl = TemplateEngine::parse('view/dialog/DeleteTopic.dialog.ctpl', array('topicID' => $getID));
         if(!TemplateEngine::hasFailed($dialogTpl))
            $dialogs .= $dialogTpl;
      }
   }
   
   // Dialogs for interactions (showing them, sending an alert, etc.)
   $interactionsTpl = TemplateEngine::parse('view/dialog/Interactions.multiple.ctpl');
   if(!TemplateEngine::hasFailed($interactionsTpl))
      $dialogs .= $interactionsTpl;
   
   // Topic header
   $headerTplInput = TopicHeaderIR::process($topic);
   $headerTpl = TemplateEngine::parse('view/content/TopicHeader.ctpl', $headerTplInput);
   if(!TemplateEngine::hasFailed($headerTpl))
      $finalTplInput['header'] = $headerTpl;
   else
      WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du sujet');

   // Checks online status of pseudonyms in "author" field of the retrieved posts
   $listedUsers = array();
   $listedAdmins = array();
   for($i = 0; $i < count($posts); $i++)
   {
      if($posts[$i]['posted_as'] === 'regular user' && !in_array($posts[$i]['author'], $listedUsers))
         array_push($listedUsers, $posts[$i]['author']);
      else if($posts[$i]['posted_as'] === 'administrator' && !in_array($posts[$i]['author'], $listedAdmins))
         array_push($listedAdmins, $posts[$i]['author']);
   }
   $online = null;
   try
   {
      $online = User::checkOnlineStatus($listedUsers, $listedAdmins);
   }
   catch(Exception $e) {}
   
   // Renders the posts
   $fullInput = array();
   for($i = 0; $i < count($posts); $i++)
   {
      if($posts[$i]['posted_as'] !== 'anonymous')
      {
         if($online != null && in_array($posts[$i]['author'], $online))
            $posts[$i]['online'] = true;
         $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($firstPost + $i + 1));
         $posts[$i]['content'] = MessageParsing::parseReferences($posts[$i]['content'], PathHandler::topicURL($topic->getAll(), '[]'));
      }
      $postIR = PostIR::process($posts[$i], ($firstPost + $i + 1), !Utils::check($topic->get('is_locked')));
      array_push($fullInput, $postIR);
   }
   $fullInput = Utils::removeSeconds($fullInput);
   
   $postsTpl = TemplateEngine::parseMultiple('view/content/Post.ctpl', $fullInput);
   if(!TemplateEngine::hasFailed($postsTpl))
   {
      for($i = 0; $i < count($postsTpl); $i++)
         $finalTplInput['posts'] .= $postsTpl[$i];
   }
   else
      WebpageHandler::wrap($postsTpl, 'Une erreur est survenue lors de la lecture du sujet');
   
   // Reply form if anonymous are authorized or if user is logged AND if the topic is not locked
   if(!Utils::check($topic->get('is_locked')) && ($anonymousPosting || (LoggedUser::isLoggedIn())))
   {
      $formTplInput = array('errors' => '', 
      'topicID' => $getID, 
      'anonPseudoStatus' => 'new', 
      'showFormattingUI' => 'no', 
      'content' => '', 
      'uploadOptions' => '', 
      'formEnd' => 'anon');
      
      if(LoggedUser::isLoggedIn())
      {
         $formTplInput['anonPseudoStatus'] = '';
         $formTplInput['formEnd'] = 'askAdvancedMode';
         $formTplInput['showFormattingUI'] = 'yes';
         WebpageHandler::addCSS('preview');
         WebpageHandler::addJS('formatting');
         WebpageHandler::addJS('preview');
         
         // Dialogs for formatting
         $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
         if(!TemplateEngine::hasFailed($formattingDialogsTpl))
            $dialogs .= $formattingDialogsTpl;
      }
      else
      {
         $anonPseudo = Anonymous::getPseudo();
         if(strlen($anonPseudo) > 3 && strlen($anonPseudo) < 21)
         {
            $formTplInput['anonPseudoStatus'] = 'existing||';
            $formTplInput['anonPseudoStatus'] .= $anonPseudo;
         }
      }
      
      $formTpl = TemplateEngine::parse('view/content/PostMessage.form.ctpl', $formTplInput);
      if(!TemplateEngine::hasFailed($formTpl))
         $finalTplInput['replyForm'] = $formTpl;
      else
         WebpageHandler::wrap($formTpl, 'Une erreur est survenue lors de la lecture du sujet');
   }
   
   // Generates the whole page
   $display = TemplateEngine::parse('view/content/Topic.composite.ctpl', $finalTplInput);
   WebpageHandler::wrap($display, 'Sujet: '.$topic->get('title').'', $dialogs);
}
else
{
   $tpl = TemplateEngine::parse('view/content/Topic.fail.ctpl', array('error' => 'wrongURL'));
   WebpageHandler::wrap($tpl, 'Sujet introuvable');
}

?>

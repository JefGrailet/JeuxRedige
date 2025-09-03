<?php

/**
* This script is used to display the history of messages of a given user. It is publicly 
* accessible and the display provides links to relocate the messages within the topics they were 
* posted in.
*/

require './libraries/Header.lib.php';
require './libraries/MessageParsing.lib.php';
require './model/User.class.php';
require './view/intermediate/Post.ir.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['author']))
{
   $getUserString = Utils::secure($_GET['author']);
   
   // Prepares the input for the final page
   $finalTplInput = array('user' => '',
   'editUserLink' => '',
   'pageConfig' => '',
   'posts' => '');

   // Retrieves user's data if possible; stops and displays appropriate error message otherwise
   $user = null;
   $posts = null;
   try
   {
      $user = new User($getUserString);
      $nbPosts = $user->countPosts();
      
      $finalTplInput['user'] = $user->get('pseudo');
   
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
      
      // Link to the page to edit this user, if allowed to
      if(LoggedUser::isLoggedIn() && Utils::check(LoggedUser::$data['can_edit_users']))
      {
         $finalTplInput['editUserLink'] = './EditUser.php?user='.$user->get('pseudo');
      }
   
      $finalTplInput['pagesConfig'] = WebpageHandler::$miscParams['posts_per_page'].'|'.$nbPosts.'|'.$currentPage;
      $finalTplInput['pagesConfig'] .= '|./Posts.php?author='.$user->get('pseudo').'&amp;page=[]';
      $finalTplInput['pagesConfig'] .= '|GetUserPosts.php?author='.$user->get('pseudo');
      $posts = $user->getPosts($firstPost, WebpageHandler::$miscParams['posts_per_page'], false);
   }
   catch(Exception $e)
   {
      // Problematic exceptions: everything besides "No message has been found"
      if(strstr($e->getMessage(), 'No message has been found') == FALSE)
      {
         $tplInput = array('error' => 'dbError');
         if(strstr($e->getMessage(), 'does not exist') != FALSE)
            $tplInput['error'] = 'nonexistingUser';
         $tpl = TemplateEngine::parse('view/user/Posts.fail.ctpl', $tplInput);
         WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
      }
   }
   
   // Some alternative display (no post to show)
   if($posts == NULL)
   {
      $tpl = TemplateEngine::parse('view/user/Posts.empty.ctpl', array('user' => $user->get('pseudo')));
      WebpageHandler::wrap($tpl, 'Historique des messages de '.$user->get('pseudo'));
   }

   // Messages
   $fullInput = array();
   for($i = 0; $i < count($posts); $i++)
   {
      $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($firstPost + $i + 1));
      $posts[$i]['content'] = MessageParsing::removeReferences($posts[$i]['content']);
   
      $postIR = PostIR::process($posts[$i], $firstPost + $i + 1, false);
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
      WebpageHandler::wrap($postsTpl, 'Une erreur est survenue lors de la lecture des messages');
   
   // Webpage settings
   WebpageHandler::addCSS('topic');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('topic_medium');
   WebpageHandler::addJS('topic_interaction');
   WebpageHandler::addJS('jquery.visible');
   WebpageHandler::addJS('pages');
   WebpageHandler::addJS('post_censorship');
   WebpageHandler::noContainer();
   
   // Dialog for showing interactions
   $dialogs = '';
   $interactionsTpl = TemplateEngine::parse('view/dialog/Interactions.dialog.ctpl');
   if(!TemplateEngine::hasFailed($interactionsTpl))
      $dialogs .= $interactionsTpl;
   
   // Generates the whole page
   $display = TemplateEngine::parse('view/user/Posts.ctpl', $finalTplInput);
   WebpageHandler::wrap($display, 'Historique des messages de '.$user->get('pseudo'), $dialogs);
}
else
{
   $tplInput = array('error' => 'missingUser');
   $tpl = TemplateEngine::parse('view/user/Posts.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Impossible de retrouver l\'utilisateur');
}
?>

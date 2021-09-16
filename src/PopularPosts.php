<?php

/**
* This script displays the (un)popular or pinned posts of a given topic, in addition to its header 
* (plus games, if any).
*/

require './libraries/Header.lib.php';

// Constants
define("POPULAR", 1);
define("UNPOPULAR", 2);
define("PINNED", 3);

// Detects the section
$section = POPULAR;
$sectionStr = 'popular';
if(!empty($_GET['section']))
{
   if($_GET['section'] === 'unpopular')
   {
      $section = UNPOPULAR;
      $sectionStr = 'unpopular';
   }
   else if($_GET['section'] === 'pinned')
   {
      $section = PINNED;
      $sectionStr = 'pins';
   }
}

require './view/intermediate/TopicHeader.ir.php';
require './view/intermediate/Post.ir.php';
require './model/Topic.class.php';
require './model/Post.class.php';
require './model/User.class.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);
   
   // Prepares the input for the template
   $finalTplInput = array('header' => '', 
   'pageConfig' => '', 
   'posts' => '');

   // Obtains topic and related data
   $nbPosts = 0;
   try
   {
      $topic = new Topic($getID);
      $topic->loadMetadata();
      if($section == UNPOPULAR)
         $nbPosts = $topic->countPopularPosts(false);
      else if($section == PINNED)
         $nbPosts = $topic->getNbPins();
      else
         $nbPosts = $topic->countPopularPosts();
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
   
   $finalTplInput['topicLink'] = PathHandler::topicURL($topic->getAll());
   
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
   $headerTplInput = TopicHeaderIR::process($topic, $sectionStr);
   $headerTpl = TemplateEngine::parse('view/content/TopicHeader.ctpl', $headerTplInput);
   if(!TemplateEngine::hasFailed($headerTpl))
      $finalTplInput['header'] = $headerTpl;
   else
      WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du sujet');
   
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
   WebpageHandler::changeContainer('topicContent');
   
   if($section == PINNED && (!LoggedUser::isLoggedIn()))
   {
      $finalTplInput['error'] = 'notLoggedIn';
      $tpl = TemplateEngine::parse('view/content/PopularPosts.fail.ctpl', $finalTplInput);
      WebpageHandler::wrap($tpl, 'Vous devez être connecté');
   }
   
   // Messages for when there is no (un)popular posts
   $popularPosts = $topic->getBufferedFeaturedPosts();
   if($section != PINNED && $popularPosts['popular'] == 0 && $popularPosts['unpopular'] == 0)
   {
      $finalTplInput['error'] = 'nothing';
      $tpl = TemplateEngine::parse('view/content/PopularPosts.fail.ctpl', $finalTplInput);
      WebpageHandler::wrap($tpl, 'Pas de message (im)populaire', $dialogs);
   }
   else if($section == UNPOPULAR && $popularPosts['unpopular'] == 0)
   {
      $finalTplInput['error'] = 'noNegative';
      $tpl = TemplateEngine::parse('view/content/PopularPosts.fail.ctpl', $finalTplInput);
      WebpageHandler::wrap($tpl, 'Pas de message impopulaire', $dialogs);
   }
   else if($section == POPULAR && $popularPosts['popular'] == 0)
   {
      $finalTplInput['error'] = 'noPositive';
      $tpl = TemplateEngine::parse('view/content/PopularPosts.fail.ctpl', $finalTplInput);
      WebpageHandler::wrap($tpl, 'Pas de message populaire', $dialogs);
   }
   else if($section == PINNED && $nbPosts == 0)
   {
      $finalTplInput['error'] = 'noPins';
      $tpl = TemplateEngine::parse('view/content/PopularPosts.fail.ctpl', $finalTplInput);
      WebpageHandler::wrap($tpl, 'Pas de message favori', $dialogs);
   }

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
   $finalTplInput['pagesConfig'] = WebpageHandler::$miscParams['posts_per_page'].'|'.$nbPosts.'|'.$currentPage;
   $finalTplInput['pagesConfig'] .= '|./PopularPosts.php?id_topic='.$getID.'&section='.$sectionStr.'&page=[]';
   $finalTplInput['pagesConfig'] .= '|GetPopularPosts.php?id_topic='.$getID.'&section='.$sectionStr;
   
   // Gets the (un)popular posts
   $posts = null;
   try
   {
      if($section == PINNED)
         $posts = $topic->getPinnedPosts($firstPost, WebpageHandler::$miscParams['posts_per_page']);
      else if($section == UNPOPULAR)
         $posts = $topic->getPopularPosts($firstPost, WebpageHandler::$miscParams['posts_per_page'], false);
      else
         $posts = $topic->getPopularPosts($firstPost, WebpageHandler::$miscParams['posts_per_page']);
      Post::getUserInteractions($posts);
   }
   catch(Exception $e)
   {
      $finalTplInput['error'] = 'dbError';
      $tpl = TemplateEngine::parse('view/content/PopularPosts.fail.ctpl', $finalTplInput);
      WebpageHandler::wrap($tpl, 'Pas de message populaire', $dialogs);
   }
   
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
   
   // Formats the posts
   $fullInput = array();
   for($i = 0; $i < count($posts); $i++)
   {
      if($posts[$i]['posted_as'] !== 'anonymous')
      {
         if($online != null && in_array($posts[$i]['author'], $online))
            $posts[$i]['online'] = true;
         $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($firstPost + $i + 1));
         $posts[$i]['content'] = MessageParsing::removeReferences($posts[$i]['content']);
      }
   
      $postIR = PostIR::process($posts[$i], ($firstPost + $i + 1), !Utils::check($topic->get('is_locked')));
      array_push($fullInput, $postIR);
   }
   
   $postsTpl = TemplateEngine::parseMultiple('view/content/Post.ctpl', $fullInput);
   if(!TemplateEngine::hasFailed($postsTpl))
   {
      for($i = 0; $i < count($postsTpl); $i++)
         $finalTplInput['posts'] .= $postsTpl[$i];
   }
   else
      WebpageHandler::wrap($postsTpl, 'Une erreur est survenue lors de la lecture des messages');
   
   // Generates the whole page
   $display = TemplateEngine::parse('view/content/PopularPosts.composite.ctpl', $finalTplInput);
   if($section == PINNED)
      WebpageHandler::wrap($display, 'Messages favoris du sujet "'.$topic->get('title').'"', $dialogs);
   else if($section == UNPOPULAR)
      WebpageHandler::wrap($display, 'Messages impopulaires du sujet "'.$topic->get('title').'"', $dialogs);
   else
      WebpageHandler::wrap($display, 'Messages populaires du sujet "'.$topic->get('title').'"', $dialogs);
}
else
{
   $tpl = TemplateEngine::parse('view/content/Topic.fail.ctpl', array('error' => 'wrongURL'));
   WebpageHandler::wrap($tpl, 'Sujet introuvable');
}

?>

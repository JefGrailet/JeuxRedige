<?php

/**
* Script to get a set of posts from a topic, provided its ID, an offset and an amount of posts to 
* retrieve.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Topic.class.php';
require '../model/Post.class.php';
require '../model/User.class.php';
require '../libraries/MessageParsing.lib.php';
require '../view/intermediate/Post.ir.php';

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

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']) && 
   isset($_GET['offset']) && preg_match('#^([0-9]+)$#', $_GET['offset']) && 
   !empty($_GET['amount']) && preg_match('#^([0-9]+)$#', $_GET['amount']))
{
   $topicID = intval(Utils::secure($_GET['id_topic']));
   $offset = intval(Utils::secure($_GET['offset']));
   $amount = intval(Utils::secure($_GET['amount']));
   
   $resStr = '';
   try
   {
      $topic = new Topic($topicID);
      if($section == PINNED)
         $posts = $topic->getPinnedPosts($offset, $amount);
      else if($section == UNPOPULAR)
         $posts = $topic->getPopularPosts($offset, $amount, false);
      else
         $posts = $topic->getPopularPosts($offset, $amount);
      Post::getUserInteractions($posts);
      
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
      $online = User::checkOnlineStatus($listedUsers, $listedAdmins);
      
      // Formats the posts
      $fullInput = array();
      for($i = 0; $i < count($posts); $i++)
      {
         if($posts[$i]['posted_as'] !== 'anonymous')
         {
            if($online != null && in_array($posts[$i]['author'], $online))
               $posts[$i]['online'] = true;
            $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($offset + $i + 1));
            $posts[$i]['content'] = MessageParsing::parseReferences($posts[$i]['content'], PathHandler::topicURL($topic->getAll(), '[]'));
         }
         $postIR = PostIR::process($posts[$i], ($offset + $i + 1), !Utils::check($topic->get('is_locked')));
         array_push($fullInput, $postIR);
      }
      $fullInput = Utils::removeSeconds($fullInput);
      
      // Renders the posts
      $postsTpl = TemplateEngine::parseMultiple('view/content/Post.ctpl', $fullInput);
      if(!TemplateEngine::hasFailed($postsTpl))
      {
         for($i = 0; $i < count($postsTpl); $i++)
            $resStr .= $postsTpl[$i];
      }
      else
         $resStr = 'Template error';
   }
   catch(Exception $e)
   {
      if($e->getMessage() === 'No message has been found.')
         $resStr = 'No message';
      else
         $resStr = 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}
else
{
   header('Content-Type: text/html; charset=UTF-8');
   echo 'Bad arguments';
}

?>

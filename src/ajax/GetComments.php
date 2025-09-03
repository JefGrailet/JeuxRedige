<?php

/**
* Script to get all posts from a topic in reaction to some article, provided its ID and display 
* them in a simplified manner as comments to the article.
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
require '../view/intermediate/Comment.ir.php';

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $topicID = intval(Utils::secure($_GET['id_topic']));

   $resStr = '';
   try
   {
      $topic = new Topic($topicID);
      $posts = $topic->getPosts(1, $topic->countPosts()); // 1 because we ignore the first message (automatic)
      
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
            $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($i + 1));
            $posts[$i]['content'] = MessageParsing::removeReferences($posts[$i]['content']);
         }
         $postIR = CommentIR::process($posts[$i]);
         array_push($fullInput, $postIR);
      }
      $fullInput = Utils::removeSeconds($fullInput);
      
      // Renders the posts
      $postsTpl = TemplateEngine::parseMultiple('view/content/Comment.ctpl', $fullInput);
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

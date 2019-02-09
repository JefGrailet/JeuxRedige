<?php

/**
* Script to get all posts of a topic after a certain index. It is used in the context of automatic 
* refresh.
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

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']) && 
   !empty($_GET['offset']) && preg_match('#^([0-9]+)$#', $_GET['offset']) &&
   !empty($_GET['per_page']) && preg_match('#^([0-9]+)$#', $_GET['per_page']))
{
   $topicID = intval(Utils::secure($_GET['id_topic']));
   $offset = intval(Utils::secure($_GET['offset']));
   $perPage = intval(Utils::secure($_GET['per_page']));

   $resStr = '';
   try
   {
      $topic = new Topic($topicID);
      $posts = $topic->getPosts($offset, 5000);
      $nbNewPosts = count($posts);
      
      /*
      * Note: 5000 looks silly, but this is actually recommended by MySQL doc (with an even 
      * ridiculously large number): https://dev.mysql.com/doc/refman/5.6/en/select.html
      */
      
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
      
      $pagingNeeded = false;
      $postCount = ($offset % $perPage);
      if($postCount == 0 || ($postCount + $nbNewPosts) > $perPage)
      {
         $resStr .= "New pages\n";
         $pagingNeeded = true;
      }
      
      // Renders the posts
      $postsTpl = TemplateEngine::parseMultiple('view/content/Post.ctpl', $fullInput);
      if(!TemplateEngine::hasFailed($postsTpl))
      {
         $curPage = ceil($offset / $perPage);
         if($offset % $perPage == 0)
            $curPage++;
         if($pagingNeeded)
            $resStr .= "<div class=\"page\" data-page=\"".$curPage."\">\n";
         
         for($i = 0; $i < count($postsTpl); $i++)
         {
            $resStr .= $postsTpl[$i];
            $postCount++;
            
            if($postCount == $perPage && $pagingNeeded)
            {
               $resStr .= "</div>\n";
               if($i < (count($postsTpl) - 1))
               {
                  $curPage++;
                  $resStr .= "<div class=\"page\" data-page=\"".$curPage."\">\n";
               }
               $postCount = 0;
            }
         }
         
         if($pagingNeeded)
            $resStr .= "</div>\n";
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

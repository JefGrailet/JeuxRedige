<?php

/**
* Script to get a set of posts from a given user, provided its pseudonym, an offset and an amount 
* of posts to retrieve.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/User.class.php';
require '../libraries/MessageParsing.lib.php';
require '../view/intermediate/Post.ir.php';

if(!empty($_GET['author']) && 
   isset($_GET['offset']) && preg_match('#^([0-9]+)$#', $_GET['offset']) && 
   !empty($_GET['amount']) && preg_match('#^([0-9]+)$#', $_GET['amount']))
{
   $getPseudo = Utils::secure($_GET['author']);
   $offset = intval(Utils::secure($_GET['offset']));
   $amount = intval(Utils::secure($_GET['amount']));

   $resStr = '';
   try
   {
      $user = new User($getPseudo);
      $posts = $user->getPosts($offset, $amount, false);
      
      // Messages
      $fullInput = array();
      for($i = 0; $i < count($posts); $i++)
      {
         $posts[$i]['content'] = MessageParsing::parse($posts[$i]['content'], ($offset + $i + 1));
         $posts[$i]['content'] = MessageParsing::removeReferences($posts[$i]['content']);
      
         $postIR = PostIR::process($posts[$i], $offset + $i + 1, false);
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

<?php

/**
* Script to get a list of all users who interacted with a given post and what they did.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Post.class.php';

if(!empty($_GET['id_post']) && preg_match('#^([0-9]+)$#', $_GET['id_post']))
{
   $postID = intval(Utils::secure($_GET['id_post']));

   $resStr = '';
   $post = null;
   try
   {
      $post = new Post($postID);
      $interactions = $post->listInteractions();
      
      // Small conversion of the data for the template
      $keys = array_keys($interactions);
      if(count($keys) > 0)
      {
         $fullInput = array();
         for($i = 0; $i < count($keys); $i++)
         {
            $intermediate = array('voterAvatar' => PathHandler::getAvatarSmall($keys[$i]),
                                  'voter' => $keys[$i], 
                                  'vote' => $interactions[$keys[$i]]['vote'], 
                                  'report' => $interactions[$keys[$i]]['report']);
            
            array_push($fullInput, $intermediate);
         }
         
         $postsTpl = TemplateEngine::parseMultiple('view/content/PostInteraction.item.ctpl', $fullInput);
         if(!TemplateEngine::hasFailed($postsTpl))
         {
            for($i = 0; $i < count($postsTpl); $i++)
               $resStr .= $postsTpl[$i]."\n";
         }
         else
         {
            $resStr = 'template error';
         }
      }
      else
      {
         $resStr = 'no interaction';
      }
   }
   catch(Exception $e)
   {
      $resStr = 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $resStr;
}

?>

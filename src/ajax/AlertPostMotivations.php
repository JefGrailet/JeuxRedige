<?php

/**
* Script to list all previous alert motivations for a given post, in order to provide pre-existing 
* motivations to other users willing to alert the same post rather than always asking them to give 
* a whole new motivation.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Post.class.php';

if(!empty($_POST['id_post']) && preg_match('#^([0-9]+)$#', $_POST['id_post']))
{
   $idPost = Utils::secure($_POST['id_post']);
   
   try
   {
      $post = new Post($idPost);
      $arr = $post->listAlertMotivations();
      
      $motiv = array_keys($arr);
      $res = "";
      if(count($motiv) == 0)
      {
         $res = 'None';
      }
      else
      {
         for($i = 0; $i < count($motiv); $i++)
         {
            $res .= '<option value="'.$motiv[$i].'">'.$motiv[$i].' ('.$arr[$motiv[$i]].')</option>';
            $res .= "\n";
         }
      }
      
      header('Content-Type: text/html; charset=UTF-8');
      echo $res;
   }
   catch(Exception $e)
   {
      exit();
   }
}

?>

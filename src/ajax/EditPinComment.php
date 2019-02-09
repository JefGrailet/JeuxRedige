<?php

/**
* Script to edit the comment of a pin via AJAX.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Pin.class.php';

if(!empty($_POST['id_post']) && preg_match('#^([0-9]+)$#', $_POST['id_post']) && !empty($_POST['comment']))
{
   $postID = Utils::secure($_POST['id_post']);
   $newComment = Utils::secure($_POST['comment']);
   
   $existingPin = null;
   try
   {
      $existingPin = new Pin($postID);
   }
   catch(Exception $e)
   {
      if($e->getMessage() !== 'Pin does not exist.')
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
   }
   
   $res = "";
   try
   {
      if($existingPin != null)
      {
         $existingPin->edit($newComment);
         $res = "OK";
      }
      else
      {
         $res = "Not pinned";
      }
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $res;
}

?>

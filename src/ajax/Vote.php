<?php

/**
* Script to record a vote or undo it via AJAX.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Vote.class.php';

if(isset($_POST['vote']) && !empty($_POST['id_post']) && preg_match('#^([0-9]+)$#', $_POST['id_post']))
{
   $postID = Utils::secure($_POST['id_post']);
   $vote = intval(Utils::secure($_POST['vote']));
   
   $existingVote = null;
   try
   {
      $existingVote = new Vote($postID);
   }
   catch(Exception $e)
   {
      if($e->getMessage() !== 'Vote does not exist.')
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'DB error';
      }
   }
   
   try
   {
      if($existingVote != null)
         $existingVote->delete();
      else
         Vote::insert($postID, ($vote > 0 ? true : false));
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo 'OK';
}

?>

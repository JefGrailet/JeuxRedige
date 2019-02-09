<?php

/**
* Script to record an alert on an offensive/inappropriate post through AJAX. As it is not possible 
* to undo a report, the mechanism is simpler than the scoring system at every level. The only 
* particularity of this script is to send back an integer: 1 if the bad score has crossed the 
* threshold, 0 in all other successful cases, and -1 in case of malfunction. The inputs are the 
* post ID and the motivation of the alert (as a string).
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Post.class.php';
require '../model/Alert.class.php';

if(!empty($_POST['id_post']) && preg_match('#^([0-9]+)$#', $_POST['id_post']) && !empty($_POST['motiv']))
{
   $idPost = Utils::secure($_POST['id_post']);
   $motivation = Utils::secure($_POST['motiv']);
   if(strlen($motivation) > 100)
      $motivation = substr($motivation, 0, 100);
   
   // Checks an alert by the same user for the same post exists
   $exists = false;
   try
   {
      $alert = new Alert($idPost);
      $exists = true;
   }
   catch(Exception $e) { }
   
   if($exists)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'Duplicate alert';
      exit();
   }
   
   try
   {
      $post = new Post($idPost);
      $currentScore = $post->get('bad_score');
      $newScore = Alert::insert($post, $motivation);
      
      header('Content-Type: text/html; charset=UTF-8');
      if($newScore == $currentScore)
         echo -1; // Error case
      else if($currentScore < 10 && $newScore >= 10)
         echo 1;
      else
         echo 0;
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo $e->getMessage();
   }
}

?>

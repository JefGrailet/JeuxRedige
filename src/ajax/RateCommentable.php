<?php

/**
* Script to record an appreciation for a commentable via AJAX.
*/

if($_SERVER['REQUEST_METHOD'] !== 'POST')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Commentable.class.php';
require '../view/intermediate/Commentable.ir.php';

if(isset($_POST['rating']) && !empty($_POST['id_commentable']) && preg_match('#^([0-9]+)$#', $_POST['id_commentable']))
{
   $commentableID = Utils::secure($_POST['id_commentable']);
   $userRating = Utils::secure($_POST['rating']);
   
   /*
   * N.B.: "rating" isn't an integer because there might be several possible ratings in the 
   * future, i.e., other than "relevant"/"irrelevant".
   */
   
   $commentable = null;
   $curRating = '';
   try
   {
      $commentable = new Commentable($commentableID);
      $curRating = $commentable->getUserRating();
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      if($e->getMessage() !== 'Commentable does not exist.')
         echo 'DB error';
      else
         echo 'Missing content';
      exit();
   }
   
   if($curRating == 'notLogged' || $curRating == 'author')
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'Forbidden rating';
      exit();
   }
   
   $newRatings = null;
   try
   {
      if($curRating !== 'none')
         $commentable->unrate();
      else
         $commentable->rate($userRating === 'relevant' ? true : false);
      $newRatings = $commentable->getRatings();
   }
   catch(Exception $e)
   {
      header('Content-Type: text/html; charset=UTF-8');
      echo 'DB error';
      exit();
   }
   
   header('Content-Type: text/html; charset=UTF-8');
   echo '<div id="ratingResult">'."\n";
   echo '<div id="newScore">'.CommentableIR::processRatings($commentable)."</div>\n";
   echo '<div id="newRelevantRatings">'.CommentableIR::processRaters($newRatings[0])."</div>\n";
   echo '<div id="newIrrelevantRatings">'.CommentableIR::processRaters($newRatings[1])."</div>\n";
   echo "</div>\n";
}

?>

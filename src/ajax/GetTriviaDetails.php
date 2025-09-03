<?php

/**
* Script to get the details on a specific piece of trivia. This script is part of an asynchronous 
* display scheme used for game pages.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Trivia.class.php';
require '../view/intermediate/Trivia.ir.php';

if(!empty($_GET['id_trivia']) && preg_match('#^([0-9]+)$#', $_GET['id_trivia']))
{
   $triviaID = intval(Utils::secure($_GET['id_trivia']));
   
   $trivia = NULL;
   try
   {
      $trivia = new Trivia($triviaID);
      $trivia->getTopic(); // Ditto for associated topic
      $trivia->getUserRating();
      $trivia->getRatings();
   }
   catch(Exception $e)
   {
      $resStr = 'DB error';
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $resStr = 'Missing piece';
      header('Content-Type: text/html; charset=UTF-8');
      echo $resStr;
      exit();
   }
   $resStr = '';
   
   /*
   * Writes the output of the script, as a series of DIVs separating each part of the requested 
   * details (i.e., one DIV for dates, another for detailed ratings, etc.) to ease manipulation.
   */
   
   $output = '<div id="triviaDetails">'."\n";
   
   // Details on the piece itself
   $output .= '<div id="detailsOnDates">'."\n".CommentableIR::processDates($trivia)."\n</div>\n";
   $output .= '<div id="detailsOnLinks">'."\n".TriviaIR::processLinks($trivia)."\n</div>\n";
   $output .= '<div id="upToDateRatings">'."\n".CommentableIR::processRatings($trivia)."\n</div>\n";
   
   // Details for the rating process (some functions come from Commentable.ir.php)
   if(LoggedUser::isLoggedIn() && $trivia->get('pseudo') !== LoggedUser::$data['pseudo'])
      $output .= '<div id="userCanRate">yes</div>'."\n";
   else
      $output .= '<div id="userCanRate">no</div>'."\n";
   $output .= '<div id="ratingButtonStyle">'.CommentableIR::ratingButtonStyle($trivia)."</div>\n";
   $output .= '<div id="relevantRatings">'.CommentableIR::processRaters($trivia->getBufferedRelevantRatings())."</div>\n";
   $output .= '<div id="irrelevantRatings">'.CommentableIR::processRaters($trivia->getBufferedIrrelevantRatings())."</div>\n";
   
   $output .= '</div>';
   
   header('Content-Type: text/html; charset=UTF-8');
   echo $output;
}
else
{
   header('Content-Type: text/html; charset=UTF-8');
   echo 'Bad arguments';
}

?>

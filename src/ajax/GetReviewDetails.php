<?php

/**
* Script to get the details on a specific review. This script is part of an asynchronous display 
* scheme used for game pages.
*/

if($_SERVER['REQUEST_METHOD'] !== 'GET')
{
   exit();
}

require '../libraries/Header.lib.php';
require '../model/Review.class.php';
require '../model/Trope.class.php';
require '../view/intermediate/Review.ir.php';

if(!empty($_GET['id_review']) && preg_match('#^([0-9]+)$#', $_GET['id_review']))
{
   $reviewID = intval(Utils::secure($_GET['id_review']));
   
   $review = NULL;
   $tropes = NULL;
   try
   {
      $review = new Review($reviewID);
      $tropes = $review->getTropes();
      $review->getArticle(); // Loads associated article data
      $review->getTopic(); // Ditto for associated topic
      $review->getUserRating();
      $review->getRatings();
   }
   catch(Exception $e)
   {
      $resStr = 'DB error';
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $resStr = 'Missing review';
      header('Content-Type: text/html; charset=UTF-8');
      echo $resStr;
      exit();
   }
   $resStr = '';
   
   // Rendered tropes
   $thumbnails = '';
   if(count($tropes) > 0)
   {
      $tropesInput = array();
      for($i = 0; $i < count($tropes); $i++)
         array_push($tropesInput, TropeIR::process($tropes[$i]));
      $tropesOutput = TemplateEngine::parseMultiple('view/content/Trope.ctpl', $tropesInput);
      if(TemplateEngine::hasFailed($tropesOutput))
      {
         header('Content-Type: text/html; charset=UTF-8');
         echo 'Bad templating';
         exit();
      }
      else
      {
         for($i = 0; $i < count($tropesOutput); $i++)
            $thumbnails .= $tropesOutput[$i];
      }
   }
   
   /*
   * Writes the output of the script, as a series of DIVs separating each part of the requested 
   * details (i.e., one DIV for dates, another for detailed tropes, etc.) to ease manipulation.
   */
   
   $output = '<div id="reviewDetails">'."\n";
   
   // Details on the review itself
   $output .= '<div id="detailsOnDates">'."\n".CommentableIR::processDates($review)."\n</div>\n";
   $output .= '<div id="detailsOnLinks">'."\n".ReviewIR::processLinks($review)."\n</div>\n";
   $output .= '<div id="upToDateRatings">'."\n".CommentableIR::processRatings($review)."\n</div>\n";
   $output .= '<div id="detailsOnTropes">'."\n".$thumbnails."\n</div>\n";
   
   // Details for the rating process (some functions come from Commentable.ir.php)
   if(LoggedUser::isLoggedIn() && $review->get('pseudo') !== LoggedUser::$data['pseudo'])
      $output .= '<div id="userCanRate">yes</div>'."\n";
   else
      $output .= '<div id="userCanRate">no</div>'."\n";
   $output .= '<div id="ratingButtonStyle">'.CommentableIR::ratingButtonStyle($review)."</div>\n";
   $output .= '<div id="relevantRatings">'.CommentableIR::processRaters($review->getBufferedRelevantRatings())."</div>\n";
   $output .= '<div id="irrelevantRatings">'.CommentableIR::processRaters($review->getBufferedIrrelevantRatings())."</div>\n";
   
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

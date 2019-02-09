<?php

class CommentableIR
{
   /*
   * Generates the full details about the dates of a piece of content (publication + last edition, 
   * if any) in a single string (as HTML). Is called by other IR's but can be called separately.
   * 
   * @param mixed $commentable  The commentable itself, as an object
   * @return string             The dates in HTML format
   */

   public static function processDates($commentable)
   {
      $output = '<p>';
      $output .= date('d/m/Y à H:i', Utils::toTimestamp($commentable->get('date_publication')));
      if($commentable->get('date_last_edition') !== '1970-01-01 00:00:00')
      {
         $lastModificationDate = date('d/m/Y à H:i', Utils::toTimestamp($commentable->get('date_last_edition')));
         $output .= ' (mis à jour le '.$lastModificationDate.')';
      }
      $output .= '</p>';
      return $output;
   }

   /*
   * Generates the full details about the ratings of a commentable in a single string (as HTML). 
   * Is called by other components such as ReviewIR but can be called separately.
   *
   * @param mixed $commentable  The commentable itself, as an object
   * @param bool $showClick     Optional boolean value to append a small message for commentables 
   *                            that are not rated yet
   * @return string             The ratings in HTML format
   */

   public static function processRatings($commentable, $showClick = false)
   {
      $total = $commentable->get('votes_relevant') + $commentable->get('votes_irrelevant');
      $output = '';
      if($total > 0)
      {
         $ratioPositive = floor(($commentable->get('votes_relevant') / $total) * 100);
         $output = $ratioPositive.'% d\'avis positifs ('.$total.' avis)';
      }
      else
      {
         $output = 'Pas d\'avis';
         if(LoggedUser::isLoggedIn() && $showClick)
            $output .= ' (cliquez pour voter)';
      }
      
      return $output;
   }

   /*
   * Gets the right class of the buttons the user can interact with to rate the commentable.
   * 
   * @param mixed    The commentable (as an object)
   * @return string  The class of the buttons
   */

   public static function ratingButtonStyle($commentable)
   {
      /*
      * Explanation for the last part of the expression: getUserRating() in Commentable will 
      * either make a SQL request to the DB to retrieve the user's rating, either return a value 
      * that was previously obtained through the same mean. Because the user's rating is always 
      * retrieved at the same time as the detailed list of the ratings, it makes sense to test 
      * whether there is a buffered ratings array, otherwise calling this function (which is 
      * called for each Commentable to display) will result in making a SQL request for each 
      * commentable, a behaviour we want to avoid, because getting the user's rating only makes 
      * sense when we display everything at once, not when we're in a scenario of asynchronous 
      * display where detailed ratings aren't available.
      */
      
      $testArray = $commentable->getBufferedRelevantRatings();
      if(LoggedUser::isLoggedIn() && isset($testArray))
      {
         $userRating = $commentable->getUserRating();
         if($userRating === 'relevant')
            return 'ratedRelevant';
         else if($userRating === 'irrelevant')
            return 'ratedIrrelevant';
         return 'noRating';
      }
      return 'noRating';
   }

   /*
   * Formats an array of raters into a HTML string. This function is called in CommentableIR but 
   * can also be used "as is" for some specific scripts (e.g., asynchronous display with AJAX).
   * 
   * @param string[]  An array of raters
   * @return string   The string (formatted in HTML) to display these raters
   */

   public static function processRaters($raters)
   {
      $output = '';
      if(count($raters) > 0)
      {
         for($i = 0; $i < count($raters); $i++)
         {
            if($i > 0)
               $output .= ' ';
            $avatar = PathHandler::getAvatar($raters[$i]);
            $output .= '<div class="raterAvatar"><a href="'.PathHandler::userURL($raters[$i]).'">';
            $output .= '<img src="'.$avatar.'" title="'.$raters[$i].'" ';
            $output .= 'alt="'.$raters[$i].'"/></a></div>';
         }
      }
      else
      {
         $output = '<p>Aucun vote.</p>';
      }
      return $output;
   }

   /*
   * Analyzes a Commentable object (such as a Review) to prepare the intermediate representation 
   * (or IR) for the parts of the template that will contain the ratings and the users associated 
   * to each rating.
   * 
   * @param mixed $commentable  The commentable (as an object)
   * @return mixed[]            The IR for the commentable part of the template
   */

   public static function process($commentable)
   {
      $output = array('ratingsDisplay' => 'hidden', 
      'voteActivation' => 'no', 
      'voteButtonStyle' => 'noRating', 
      'relevant' => '', 
      'irrelevant' => '');
      
      $relevantRatings = $commentable->getBufferedRelevantRatings();
      $irrelevantRatings = $commentable->getBufferedIrrelevantRatings();
      if(isset($relevantRatings) && isset($irrelevantRatings))
      {
         $output['ratingsDisplay'] = '';
         $output['voteButtonStyle'] = self::ratingButtonStyle($commentable);
         
         // One cannot rate the content if not logged in or author of the content.
         $userRating = $commentable->getUserRating();
         if($userRating !== 'notLogged' && $userRating !== 'author')
            $output['voteActivation'] = 'yes';
         
         $output['relevant'] = self::processRaters($relevantRatings);
         $output['irrelevant'] = self::processRaters($irrelevantRatings);
      }
      return $output;
   }
}

?>

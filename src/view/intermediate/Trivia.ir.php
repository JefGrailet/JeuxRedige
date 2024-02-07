<?php

require_once PathHandler::WWW_PATH().'view/intermediate/Commentable.ir.php';

class TriviaIR
{
   /*
   * Generates the full details about the topic tied to a piece of trivia, if it got one. Is 
   * called in process() but can be called separately.
   *
   * @param mixed $trivia   The piece itself, as an object
   * @return string         The links in HTML format
   */

   public static function processLinks($trivia)
   {
      // Related topic
      $output = "<p>\n";
      if($trivia->get('id_topic') != NULL)
      {
         $topic = $trivia->getBufferedTopic();
         if($topic != NULL)
         {
            $endParenthesis = 'commentaire';
            if(($topic['nb'] - 1) > 1)
               $endParenthesis .= 's';
            $output .= '<strong>Lire aussi:</strong> <a href="'.PathHandler::topicURL($topic).'" target="_blank">';
            $output .= $topic['title'].' ('.($topic['nb'] - 1).' '.$endParenthesis;
            $output .= ")</a>\n";
         }
      }
      else if(LoggedUser::isLoggedIn())
      {
         $output .= '<a href="./NewComments.php?id_content='.$trivia->get('id_commentable').'">';
         $output .= 'Commenter cette anecdote</a>'."\n";
      }
      
      $output .= "</p>\n";
      return $output;
   }

   /*
   * Converts a piece of trivia (provided as an object) into an intermediate representation, ready 
   * to be used in a template. The intermediate representation is a new array containing:
   *
   * -The ID of the piece
   * -The game thumbnail, if asked (HTML)
   * -The title of the piece, with possible icons to edit/handle the piece (HTML)
   * -The initial display of the text (should be hidden at first)
   * -The content of the piece
   * -The author's avatar and pseudo
   * -The ratings of the piece (via CommentableIR)
   *
   * @param mixed $trivia       The piece itself, as an object
   * @param bool  $fullDetails  Boolean to set to true if details (+ games) should be showed
   * @return mixed[]            The intermediate representation
   */

   public static function process($trivia, $fullDetails = false)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      
      $output = array('ID' => $trivia->get('id_commentable'), 
      'gameThumbnail' => '', 
      'title' => $trivia->get('title'), 
      'ratings' => '', 
      'contentDisplay' => 'hidden', // Default
      'content' => $trivia->get('content'), 
      'authorAvatar' => PathHandler::getAvatar($trivia->get('pseudo')), 
      'authorPseudo' => '<a href="'.PathHandler::userURL($trivia->get('pseudo')).'" target="_blank" class="authorPseudo">'.$trivia->get('pseudo').'</a>');
      
      // Game thumbnail, if relevant
      if($fullDetails)
      {
         $thumbnailPath = $webRootPath.'upload/games/'.PathHandler::formatForURL($trivia->get('game')).'/thumbnail1.jpg';
         $gameArr = array('tag' => $trivia->get('game')); // To "cheat" the PathHandler::gameURL() function
         
         $output['gameThumbnail'] = '<div class="relatedGame" style="background: url(\''.$thumbnailPath.'\') ';
         $output['gameThumbnail'] .= 'no-repeat top center;">'."\n";
         $output['gameThumbnail'] .= '<h1>'.$trivia->get('game').'</h1>'."\n";
         $output['gameThumbnail'] .= '<a href="'.PathHandler::gameURL($gameArr).'"><span></span></a>'."\n";
         $output['gameThumbnail'] .= '</div>'."\n";
         
         $output['contentDisplay'] = ''; // Everything is displayed
      }
      // If not all details are shown, encapsulate title in a link to hide/show the content
      else
      {
         $output['title'] = '<a href="javascript:void(0)" class="hideShowTrivia" title="Cliquez pour afficher/cacher">'.$output['title'].'</a>';
      }
      
      // Permanent link (or permalink)
      $output['title'] .= ' &nbsp;<a href="'.PathHandler::triviaURL($trivia->getAll()).'">';
      $output['title'] .= '<i class="icon-general_hyperlink" title="Lien permanent"></i></a>';
      
      // Edition links
      if(LoggedUser::isLoggedIn() && (Utils::check(LoggedUser::$data['can_edit_all_posts']) || $trivia->get('pseudo') === LoggedUser::$data['pseudo']))
      {
         $output['title'] .= ' <a href="./EditTrivia.php?id_trivia='.$trivia->get('id_commentable').'" target="_blank">';
         $output['title'] .= '<i class="icon-general_edit" title="Editer"></i></a>';
         
         $output['title'] .= ' <a href="./DeleteContent.php?id_content='.$trivia->get('id_commentable').'" target="_blank">';
         $output['title'] .= '<i class="icon-general_trash" title="Supprimer"></i></a>';
      }
      
      // Ratings
      $ratingsSummary = CommentableIR::processRatings($trivia, !$fullDetails); // From Commentable.ir
      if($fullDetails)
      {
         $output['ratings'] = $ratingsSummary;
      }
      else
      {
         $output['ratings'] = '<a href="javascript:void(0)" class="showDetailsLink">';
         $output['ratings'] .= $ratingsSummary.'</a>';
      }
      $output = array_merge($output, CommentableIR::process($trivia));
      
      // Dates
      $dateDetails = '';
      if($fullDetails)
         $dateDetails = '<div class="contentDates">'.CommentableIR::processDates($trivia).'</div>';
      else
         $dateDetails = '<div class="contentDates"></div>';
      
      // Links to associated article (home or external) and topic
      $readMore = '';
      if($trivia->get('id_topic') != NULL || LoggedUser::isLoggedIn())
      {
         $readMore = '<div class="readMore"';
         if(!$fullDetails)
            $readMore .= ' style="display: none;"';
         $readMore.= ">\n".self::processLinks($trivia)."</div>\n";
      }
      else
      {
         $readMore = '<div class="readMore">'."\n</div>\n";
      }
      
      // If content is ending with a div, do not end with "</p>"
      $postEnd = '</p>';
      if(substr($trivia->get('content'), -8) === "</div>\r\n")
         $postEnd = '';
      
      // Prepares the final content
      $output['content'] = $dateDetails.'
      <p>
      '.$trivia->get('content').'
      '.$postEnd.'
      '.$readMore;
      
      return $output;
   }

   /*
   * Special function that can be applied to the output of process() to add the thumbnail of the 
   * related game to the display if the calling script still use asynchronous display for the 
   * ratings, dates, etc. So far, it's only relevant for the RandomTrivia.php page.
   *
   * @param mixed $trivia   The piece of trivia itself, as an object
   * @param mixed[] $input  The output of process()
   * @return mixed[]        The same output, but adapted to feature the thumbnail of the game
   */

   public static function addGameThumbnail($trivia, $input)
   {
       // Removes the first link ("hideShow")
      $input['title'] = substr($input['title'], 89);
      $pos = strpos($input['title'], '</a>');
      $input['title'] = substr($input['title'], 0, $pos).substr($input['title'], $pos + 4);
      
      $thumbnailPath = PathHandler::HTTP_PATH().'upload/games/'.PathHandler::formatForURL($trivia->get('game')).'/thumbnail1.jpg';
      $gameArr = array('tag' => $trivia->get('game')); // To "cheat" the PathHandler::gameURL() function
      
      $input['gameThumbnail'] = '<div class="relatedGame" style="background: url(\''.$thumbnailPath.'\') ';
      $input['gameThumbnail'] .= 'no-repeat top center;">'."\n";
      $input['gameThumbnail'] .= '<h1>'.$trivia->get('game').'</h1>'."\n";
      $input['gameThumbnail'] .= '<a href="'.PathHandler::gameURL($gameArr).'"><span></span></a>'."\n";
      $input['gameThumbnail'] .= '</div>'."\n";
      
      $input['contentDisplay'] = '';
      return $input;
   }
}

?>

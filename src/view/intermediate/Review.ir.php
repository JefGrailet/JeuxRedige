<?php

require_once PathHandler::WWW_PATH().'view/intermediate/Commentable.ir.php';
require_once PathHandler::WWW_PATH().'view/intermediate/Trope.ir.php';

class ReviewIR
{
   /*
   * Generates the full details about the content related to a review (article, external content, 
   * related topic) in a single string (as HTML). Is called in process() but can be called 
   * separately.
   *
   * @param mixed $review   The review itself, as an object
   * @return string         The links in HTML format
   */

   public static function processLinks($review)
   {
      $hasLinks = ($review->get('id_article') != NULL || $review->get('external_link') != NULL || $review->get('id_topic') != NULL);
      if(!$hasLinks)
      {
         if(LoggedUser::isLoggedIn())
         {
            $output = '<p><a href="./NewComments.php?id_content='.$review->get('id_commentable').'">';
            $output .= 'Commenter cette évaluation</a></p>'."\n";
            return $output;
         }
         return '';
      }
      
      $output = "<p><strong>A lire aussi:</strong><br/>\n";
      
      // Related article (on this website or from external source)
      if($review->get('id_article') != NULL && $review->get('id_article') != 0)
      {
         $article = $review->getBufferedArticle();
         if($article != NULL)
         {
            $output .= '-Article: <a href="'.PathHandler::articleURL($article).'" target="_blank">';
            $output .= $article['title'].' - '.$article['subtitle'].'';
            $output .= '</a><br/>'."\n";
         }
      }
      else if($review->get('external_link') != NULL)
      {
         $split = explode('|', $review->get('external_link'));
         $output .= '-Contenu externe: <a href="'.$split[0].'" target="_blank">';
         $output .= $split[1].'<img src="'.PathHandler::HTTP_PATH().'res_icons/external_link.png" ';
         $output .= 'class="externalLink" alt="Lien externe"></a><br/>'."\n";
      }
      
      // Related topic
      if($review->get('id_topic') != NULL)
      {
         $topic = $review->getBufferedTopic();
         if($topic != NULL)
         {
            $endParenthesis = 'commentaire';
            if(($topic['nb'] - 1) > 1)
               $endParenthesis .= 's';
            $output .= '-Sujet: <a href="'.PathHandler::topicURL($topic).'" target="_blank">';
            $output .= $topic['title'].' ('.($topic['nb'] - 1).' '.$endParenthesis;
            $output .= ")</a><br/>\n";
         }
      }
      else if(LoggedUser::isLoggedIn())
      {
         $output .= "<br/>\n";
         $output .= '<a href="./NewComments.php?id_content='.$review->get('id_commentable').'">';
         $output .= 'Commenter cette évaluation</a>'."\n";
      }
      
      $output .= "</p>\n";
      return $output;
   }

   /*
   * Converts a review (provided as an object) into an intermediate representation, ready to be 
   * used in a template. The intermediate representation is a new array containing:
   *
   * -The ID of the review
   * -The game thumbnail, if asked (HTML)
   * -The rating color as a CSS RGB value
   * -The rating, as a string
   * -The title of the review, with possible icons to edit/handle the review (HTML)
   * -The comment
   * -The rendered trope icons (HTML)
   * -The author's avatar and pseudo
   * -The ratings of the review (via CommentableIR)
   *
   * @param mixed $review       The review itself, as an object
   * @param bool  $fullDetails  Boolean to set to true if details (+ games) should be showed
   * @return mixed[]            The intermediate representation
   */

   public static function process($review, $fullDetails = false)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      $hasLinks = ($review->get('id_article') != NULL || $review->get('external_link') != NULL || $review->get('id_topic') != NULL);
      
      $ratingsColors = array('background-color: rgb(235, 28, 36); color: white;', 
      'background-color: rgb(244, 102, 34); color: white;', 
      'background-color: rgb(251, 170, 25); color: white;', 
      'background-color: rgb(247, 238, 7); color: black;', 
      'background-color: rgb(210, 222, 36); color: black;', 
      'background-color: rgb(168, 207, 56); color: black;', 
      'background-color: rgb(145, 199, 62); color: white;', 
      'background-color: rgb(134, 197, 64); color: white;', 
      'background-color: rgb(110, 189, 68); color: white;', 
      'background-color: rgb(76, 184, 72); color: white;');
      
      $ratings = array('À éviter absolument', 
      'Médiocre', 
      'Passable', 
      'Moyen', 
      'Pour les fans du genre', 
      'Honnête', 
      'Bon', 
      'Très bon',
      'Excellent', 
      'À essayer absolument');
      
      $ratingInt = intval($review->get('rating'));
      $output = array('ID' => $review->get('id_commentable'), 
      'gameThumbnail' => '', 
      'ratingStyle' => $ratingsColors[$ratingInt - 1], 
      'ratingStr' => $ratings[$ratingInt - 1], 
      'title' => $review->get('title'), 
      'ratings' => '', 
      'comment' => $review->get('comment'), 
      'miniTropes' => '', 
      'authorAvatar' => PathHandler::getAvatar($review->get('pseudo')), 
      'authorPseudo' => '<a href="'.PathHandler::userURL($review->get('pseudo')).'" target="_blank" class="authorPseudo">'.$review->get('pseudo').'</a>');
      
      // Game thumbnail, if relevant
      if($fullDetails)
      {
         $thumbnailPath = $webRootPath.'upload/games/'.PathHandler::formatForURL($review->get('game')).'/thumbnail1.jpg';
         $gameArr = array('tag' => $review->get('game')); // To "cheat" the PathHandler::gameURL() function
         
         $output['gameThumbnail'] = '<div class="relatedGame" style="background: url(\''.$thumbnailPath.'\') ';
         $output['gameThumbnail'] .= 'no-repeat top center;">'."\n";
         $output['gameThumbnail'] .= '<h1>'.$review->get('game').'</h1>'."\n";
         $output['gameThumbnail'] .= '<a href="'.PathHandler::gameURL($gameArr).'"><span></span></a>'."\n";
         $output['gameThumbnail'] .= '</div>'."\n";
      }
      
      // Icons
      $icons = array('permalink' => $webRootPath.'res_icons/post_permalink_med.png', 
      'edit' => $webRootPath.'res_icons/post_edit_med.png', 
      'delete' => $webRootPath.'res_icons/segment_delete.png');
      
      // Permanent link (or permalink)
      $output['title'] .= ' &nbsp;<a href="'.PathHandler::reviewURL($review->getAll()).'"><img ';
      $output['title'] .= ' src="'.$icons['permalink'].'" alt="Lien permanent" title="Lien permanent"/></a>';
      
      // Edition links
      if(LoggedUser::isLoggedIn() && (Utils::check(LoggedUser::$data['can_edit_all_posts']) || $review->get('pseudo') === LoggedUser::$data['pseudo']))
      {
         $output['title'] .= ' <a href="./EditReview.php?id_review='.$review->get('id_commentable').'" target="_blank">';
         $output['title'] .= '<img class="reviewIcon" src="'.$icons['edit'].'" alt="Editer" title="Editer"/></a>';
         
         $output['title'] .= ' <a href="./DeleteContent.php?id_content='.$review->get('id_commentable').'" target="_blank">';
         $output['title'] .= '<img class="reviewIcon" src="'.$icons['delete'].'" alt="Supprimer" title="Supprimer"/></a>';
      }
      
      // Ratings
      $ratingsSummary = CommentableIR::processRatings($review, !$fullDetails); // From Commentable.ir
      if($fullDetails)
      {
         $output['ratings'] = $ratingsSummary;
      }
      else
      {
         if(LoggedUser::isLoggedIn())
         {
            $output['ratings'] = '<a href="javascript:void(0)" class="showDetailsLink">';
            $output['ratings'] .= $ratingsSummary.'</a>';
         }
         else
         {
            $output['ratings'] = $ratingsSummary;
         }
      }
      $output = array_merge($output, CommentableIR::process($review));
      
      // Dates
      $dateDetails = '';
      if($fullDetails)
         $dateDetails = '<div class="contentDates">'.CommentableIR::processDates($review).'</div>';
      else
         $dateDetails = '<div class="contentDates"></div>';
      
      // Prepares the tropes
      $tropes = explode('|', $review->get('associated_tropes'));
      if(count($tropes) > 0)
      {
         for($i = 0; $i < count($tropes); $i++)
         {
            $splitTrope = explode(',', $tropes[$i]);
            $tropeName = $splitTrope[0];
            $tropeColor = $splitTrope[1];
            
            if($i > 0)
               $output['miniTropes'] .= ' ';
            $icon = $webRootPath.'upload/tropes/'.PathHandler::formatForURL($tropeName).'.png';
            list($r, $g, $b) = sscanf($tropeColor, "#%02x%02x%02x");
            $output['miniTropes'] .= '<img src="'.$icon.'" class="miniTrope" data-rgb="'.$r.','.$g.','.$b.'" ';
            $output['miniTropes'] .= 'style="background-color: '.$tropeColor.';" ';
            if(!$fullDetails)
               $output['miniTropes'] .= 'title="'.$tropeName.'" ';
            $output['miniTropes'] .= 'alt="'.$tropeName.'"/>';
         }
         
         // If minimal display, adds an additional button to the tropes to allow user to get details
         if(!$fullDetails)
         {
            $buttonIcon = 'review_details.png';
            $buttonTitle = 'Cliquez pour plus de détails';
            if($hasLinks)
            {
               $buttonIcon = 'review_details_links.png';
               $buttonTitle = 'Cliquez pour les liens associés et les détails';
            }
            
            $output['miniTropes'] .= ' <img src="'.$webRootPath.'res_icons/'.$buttonIcon.'" ';
            $output['miniTropes'] .= 'class="showReviewDetails" title="'.$buttonTitle.'"/>';
         }
      }
      
      // Links to associated article (home or external) and topic
      $readMore = '';
      if($hasLinks || LoggedUser::isLoggedIn())
      {
         $readMore = '<div class="readMore"';
         if(!$fullDetails)
            $readMore .= ' style="display: none;"';
         $readMore.= ">\n".self::processLinks($review)."</div>\n";
      }
      else
      {
         $readMore = '<div class="readMore">'."\n</div>\n";
      }
      
      // If content is ending with a div, do not end with "</p>"
      $postEnd = '</p>';
      if(substr($review->get('comment'), -8) === "</div>\r\n")
         $postEnd = '';
      
      // Prepares the comment
      $output['comment'] = $dateDetails.'
      <p>
      '.$review->get('comment').'
      '.$postEnd.'
      '.$readMore;
      
      return $output;
   }
}

?>

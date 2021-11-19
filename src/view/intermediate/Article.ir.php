<?php

require './view/intermediate/Game.ir.php';

class ArticleIR
{
   /*
   * Generates a pseudo-random colour (as RGB value) based on a SHA1 hash of some string.
   *
   * @param string   $input  The string from which the pseudo-random colour is generated
   * @return string          The colour as a RGB value
   */

   private static function stringToColour($input)
   {
      $hash = sha1($input);
      
      $rgb = array(0, 0, 0);
      $rgb[0] = abs(hexdec(substr($hash, 0, 5))) % 128;
      $rgb[1] = abs(hexdec(substr($hash, 5, 5))) % 128;
      $rgb[2] = abs(hexdec(substr($hash, 10, 5))) % 128;
      
      // Dominant component is reinforced
      $dominant = 0;
      $index = 0;
      for($i = 0; $i < 3; $i++)
      {
         if($rgb[$i] > $dominant)
         {
            $dominant = $rgb[$i];
            $index = $i;
         }
      }
      $rgb[$index] += 50;

      // From https://gist.github.com/Pushplaybang/5432844
      // $hex = "#";
      // $hex .= str_pad(dechex($rgb[0]), 2, "0", STR_PAD_LEFT);
      // $hex .= str_pad(dechex($rgb[1]), 2, "0", STR_PAD_LEFT);
      // $hex .= str_pad(dechex($rgb[2]), 2, "0", STR_PAD_LEFT);
      
      return $rgb[0].','.$rgb[1].','.$rgb[2];
   }

   /*
   * Converts an article object (along a integer giving the currently displayed segment) into an 
   * intermediate representation, ready to be used in the actual template displaying an article. 
   * The intermediate representation is a new array containing:
   *
   * -Rendered keywords (HTML)
   * -Details about the author
   * -Avatar of the author
   * -Link to the previous section/segment (URL)
   * -List of all sections (HTML)
   * -Link to the previous section/segment (URL)
   *
   * @param Article $article   The article itself, as an object
   * @param integer $selected  The currently displayed segment
   * @return mixed[]           The intermediate representation
   */

   public static function process($article, $selected)
   {
      WebpageHandler::$miscParams['message_size'] = 'medium'; // Forces using medium avatars for the article footer
      
      $output = array('comments' => '', 
      'keywords' => '', 
      'games' => '', 
      'authorDetails' => '', 
      'authorAvatar' => PathHandler::getAvatar($article->get('pseudo')), 
      'previousSegment' => '', 
      'segmentsList' => '', 
      'nextSegment' => '');
      
      // Comments of the article
      $topic = $article->getBufferedTopic();
      if($topic != NULL && count($topic) > 0)
      {
         if($topic['nb'] > 1)
         {
            $output['comments'] = 'slider||';
            $output['comments'] .= PathHandler::topicURL($topic).'|';
            if($topic['nb'] > 2)
               $output['comments'] .= 'Commentaires ('.($topic['nb'] - 1).')|';
            else
               $output['comments'] .= 'Commentaire|';
            $output['comments'] .= PathHandler::HTTP_PATH().'PostMessage.php?id_topic='.$topic['id_topic'].'|';
            $output['comments'] .= $topic['id_topic'];
         }
         else
         {
            $output['comments'] = 'beFirst||';
            $output['comments'] .= PathHandler::HTTP_PATH().'PostMessage.php?id_topic='.$topic['id_topic'];
         }
      }
      
      // Keywords and associated games
      $keywords = $article->getBufferedKeywords();
      if(!empty($keywords))
      {
         $listKeywords = '';
         for($i = 0; $i < count($keywords); $i++)
         {
            if($i > 0)
               $listKeywords .= ' ';
            $link = PathHandler::HTTP_PATH().'SearchArticles.php?keywords='.urlencode($keywords[$i]['tag']);
            $ownColor = self::stringToColour($keywords[$i]['tag']);
            $style = 'style="background-color: rgb('.$ownColor.');" data-rgb="'.$ownColor.'"';
            $listKeywords .= '<a href="'.$link.'" target="blank" '.$style.'>'.$keywords[$i]['tag'].'</a>';
            
            if($keywords[$i]['genre'] !== NULL)
            {
               $curGame = $keywords[$i];
               $gameIR = GameIR::process($curGame);
               $gameTpl = TemplateEngine::parse('view/content/Game.ctpl', $gameIR);
            
               if(!TemplateEngine::hasFailed($gameTpl))
                  $output['games'] .= $gameTpl;
            }
         }
         $output['keywords'] = $listKeywords;
      }
      
      $typeTranslation = array('review' => 'Critique rédigée', 
      'preview' => 'Aperçu rédigé', 
      'opinion' => 'Humeur rédigée',
      'chronicle' => 'Chronique rédigée');
      
      // Details about the author
      $lastModification = Utils::toTimestamp($article->get('date_last_modifications'));
      $details = $typeTranslation[$article->get('type')].' par <a href="'.PathHandler::userURL($article->get('pseudo')).'">';
      $details .= $article->get('pseudo')."</a><br/>\n";
      if($article->isPublished())
         $details .= 'Publié le '.date('d/m/Y à H:i', Utils::toTimestamp($article->get('date_publication')))."<br/>\n";
      if($lastModification > 0)
         $details .= 'Edité le '.date('d/m/Y à H:i', $lastModification)."<br/>\n";
      if(LoggedUser::isLoggedIn())
      {
         if(LoggedUser::$data['pseudo'] === $article->get('pseudo') || Utils::check(LoggedUser::$data['can_edit_all_posts']))
         {
            $details .= '<a href="'.PathHandler::HTTP_PATH().'EditArticle.php?id_article='.$article->get('id_article').'">Editer cet article</a>'."<br/>\n";
            $details .= '<span class="editSegment">Editer ce segment</span>'."<br/>\n";
         }
      }
      $output['authorDetails'] = substr($details, 0, -6); // Removes the last "<br/>\n"
      
      // Segment navigation
      $segments = $article->getBufferedSegments();
      if(count($segments) > 1)
      {
         $fullArticleURL = PathHandler::articleURL($article->getAll());
         
         // Previous segment
         if($selected > 1)
            $output['previousSegment'] = 'yes||'.$fullArticleURL.($selected - 1).'/';
      
         // Segment list
         $listHTML = '';
         for($i = 0; $i < count($segments); $i++)
         {
            if($i == 0 && $segments[$i]['title'] == NULL)
            {
               $listHTML .= '<option value="'.($i + 1).'">Sommaire</option>'."\n";
            }
            else
            {
               if(($i + 1) == $selected)
                  $listHTML .= '<option value="'.($i + 1).'" selected>'.$segments[$i]['title'].'</option>'."\n";
               else
                  $listHTML .= '<option value="'.($i + 1).'">'.$segments[$i]['title'].'</option>'."\n";
            }
         }
         $output['segmentsList'] = 'yes||'.$listHTML;
         
         // Next segment
         if($selected < count($segments))
            $output['nextSegment'] = 'yes||'.$fullArticleURL.($selected + 1).'/';
      }
      
      return $output;
   }
}

?>

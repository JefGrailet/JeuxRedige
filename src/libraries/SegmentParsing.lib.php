<?php

/**
* This library is used to parse a segment taken from the DB (which is already parsed, to some 
* extent, in HTML) to treat features that should be easy to maintain (in the sense, changing their 
* HTML/CSS should be easy to do and shouldn't need re-parsing content which is already online). It 
* is very similar to MessageParsing.lib.php, but has some features less, and some features more 
* (due to being designed for articles).
*/

class SegmentParsing
{
   /*
   * Checks a given path is an URL.
   *
   * @param string $path  The path to checkdate
   * @return boolean      True if it is an URL, false otherwise
   */

   private static function isURL($path)
   {
      return (substr($path, 0, 7) === 'http://' OR substr($path, 0, 8) === 'https://');
   }

   /*
   * Takes a complete URL (i.e., starting with http:// or https://) and ensures that it belongs to 
   * the website, either returning the relative path to the file (URL belongs to site), either 
   * returning an empty string.
   *
   * @param string $URL  The URL
   * @return string      The URL turned into a relative path if it's here, empty string otherwise
   */

   private static function relativize($URL)
   {
      $pos = strpos($URL, PathHandler::HTTP_PATH());
      if($pos !== FALSE && $pos == 0)
      {
         return substr($URL, strlen(PathHandler::HTTP_PATH()));
      }
      
      return '';
   }

   /*
   * Parses an input segment to translate format code related to article features (e.g., quoting 
   * with a background) into HTML.
   *
   * @param string $content  The input segment (partially formatted in HTML)
   * @param string $index    The index of the segment within a succession of segments to distinct 
   *                         video elements from one segment to another; by default it is 0 (i.e. 
   *                         only one segment to display)
   * @return string          The same segment, fully formatted in HTML
   */

   public static function parse($content, $index = 0)
   {
      $parsed = $content;
      
      // Accents that can be used in: names of uploaded files, miniature comments, emphasized quotes
      $accents = "áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ";
      
      // Videos
      $videos = array();
      preg_match_all("/\!video\[([_a-zA-Z0-9\.\\/;:\?\=\-]*?)\]/", $parsed, $videos);
      
      for($i = 0; $i < count($videos[1]); $i++)
      {
         if(self::isURL($videos[1][$i]))
         {
            // Youtube
            if(strpos($videos[1][$i], 'youtu') !== FALSE)
            {
               $posID = strpos($videos[1][$i], '?v=');
               if($posID !== FALSE)
               {
                  $IDStr = substr($videos[1][$i], $posID + 3, 11);
                  
                  // In articles, videos are always embedded.
                  $videoHTML = "<iframe width=\"480\" height=\"270\" src=\"https://www.youtube.com/embed/";
                  $videoHTML .= $IDStr."\" frameborder=\"0\" allowfullscreen></iframe>\n";
                  
                  $parsed = str_replace($videos[0][$i], $videoHTML, $parsed);
               }
            }
            
            // TODO (for later): DailyMotion, Vimeo, etc.
         }
      }
      
      // Accepted values for floating
      $acceptedFloating = array('left', 'right');
      
      // Image (full image display) parsing
      $images = array();
      preg_match_all("/\!img\[([_a-zA-Z0-9".$accents."\.\\/;:\-]*?)\]/", $parsed, $images);
      
      for($i = 0; $i < count($images[1]); $i++)
      {
         $link = '';
         $ratio = 1.0;
         $floating = '';
         if(strpos($images[1][$i], ';') !== FALSE)
         {
            $exploded = explode(';', $images[1][$i]);
            $link = $exploded[0];
            
            /*
             * Dealing with ratio and float parameters. For flexibility, their order can be switched 
             * (either link;ratio;float or link;float;ratio) as long as the link comes first.
             */
            
            $tmpFloatVal = floatval($exploded[1]);
            if(in_array($exploded[1], $acceptedFloating))
            {
               $floating = $exploded[1];
               if(count($exploded) > 2)
               {
                  $tmpFloatVal2 = floatval($exploded[2]);
                  if($tmpFloatVal2 >= 0.1 && $tmpFloatVal2 <= 10.0)
                     $ratio = $tmpFloatVal2;
               }
            }
            else if($tmpFloatVal >= 0.1 && $tmpFloatVal <= 10.0)
            {
               $ratio = $tmpFloatVal;
               if(count($exploded) > 2)
               {
                  if(in_array($exploded[2], $acceptedFloating))
                     $floating = $exploded[2];
               }
            }
         }
         else
            $link = $images[1][$i];
         
         $isAnURL = self::isURL($link);
         $relativeLink = self::relativize($link);
         
         if($isAnURL && strlen($relativeLink) == 0)
         {
            $imageHTML = '<img src="'.$link.'" alt="Image externe" />';
            $parsed = str_replace($images[0][$i], $imageHTML, $parsed);
         }
         else
         {
            if($isAnURL && strlen($relativeLink) > 0)
               $link = $relativeLink;
         
            $filePath = PathHandler::WWW_PATH().$link;
            $displayPath = PathHandler::HTTP_PATH().$link;
            $extension = strtolower(substr(strrchr($filePath, '.'), 1));
            
            if(in_array($extension, Utils::UPLOAD_OPTIONS['miniExtensions']) && file_exists($filePath))
            {
               $dimensions = getimagesize($filePath);
               if($dimensions !== FALSE)
               {
                  $imageHTML = '<img src="'.$displayPath.'" alt="Upload" ';
                  if($ratio != 1.0)
                  {
                     $newWidth = $dimensions[0] * $ratio;
                     $imageHTML .= 'width="'.$newWidth.'" ';
                  }
                  if($floating !== '')
                  {
                     $imageHTML .= 'style="float: '.$floating.'; ';
                     if($floating === 'left')
                        $imageHTML .= 'margin: 0px 10px 3px 0px;';
                     else
                        $imageHTML .= 'margin: 0px 0px 3px 10px;';
                     $imageHTML .= '" ';
                  }
                  $imageHTML .= '/>';
                  
                  $parsed = str_replace($images[0][$i], $imageHTML, $parsed);
               }
            }
         }
      }
      
      // WebM/MP4 clip (full display) parsing
      $clips = array();
      preg_match_all("/\!clip\[([_a-zA-Z0-9".$accents."\.\\/;:\-]*?)\]/", $parsed, $clips);
      
      for($i = 0; $i < count($clips[1]); $i++)
      {
         $link = '';
         $floating = '';
         if(strpos($clips[1][$i], ';') !== FALSE)
         {
            $exploded = explode(';', $clips[1][$i]);
            $link = $exploded[0];
            
            // Floating
            if(in_array($exploded[1], $acceptedFloating))
            {
               $floating = $exploded[1];
            }
         }
         else
            $link = $clips[1][$i];

         if(self::isURL($link))
            $link = self::relativize($link);
      
         $filePath = PathHandler::WWW_PATH().$link;
         $displayPath = PathHandler::HTTP_PATH().$link;
         $extension = strtolower(substr(strrchr($filePath, '.'), 1));
         
         if(($extension === 'webm' || $extension === 'mp4') && file_exists($filePath))
         {
            $clipHTML = '<video ';
            if($floating !== '')
            {
               $clipHTML .= 'style="float: '.$floating.'; ';
               if($floating === 'left')
                  $clipHTML .= 'margin: 0px 10px 3px 0px;';
               else
                  $clipHTML .= 'margin: 0px 0px 3px 10px;';
               $clipHTML .= '" ';
            }
            $clipHTML .= ' controls>'."\n";
            $clipHTML .= '<source src="'.$displayPath.'" format="video/'.$extension.'">'."\n";
            $clipHTML .= '</video>';
            
            $parsed = str_replace($clips[0][$i], $clipHTML, $parsed);
         }
      }
      
      // Images/clips which can be opened in the lightbox (in addition with regular display)
      $miniatures = array();
      preg_match_all("/\!mini\[([_a-zA-Z0-9".$accents."\.\\/;:\-]*?)\](\[([a-zA-Z0-9 ".$accents."\.\,:;'\?\!\=\-\(\)\/]*)\])?/", $parsed, $miniatures);
      
      for($i = 0; $i < count($miniatures[1]); $i++)
      {
         $link = '';
         $floating = '';
         $comment = '';
         if(strpos($miniatures[1][$i], ';') !== FALSE)
         {
            $exploded = explode(';', $miniatures[1][$i]);
            $link = $exploded[0];
            $floating = $exploded[1];
         }
         else
            $link = $miniatures[1][$i];
         
         if(!in_array($floating, $acceptedFloating))
            $floating = '';
         
         // Comment is entirely optional, and is provided in $miniatures[2] and $miniatures[3]
         if(count($miniatures) > 3 && strlen($miniatures[3][$i]) > 0)
            $comment = $miniatures[3][$i];
         
         $isAnURL = self::isURL($link);
         if($isAnURL)
         {
            $relativeLink = self::relativize($link);
            if(strlen($relativeLink) > 0)
               $link = $relativeLink;
            else
               continue;
         }
         
         $filePath = PathHandler::WWW_PATH().$link;
         $displayPath = PathHandler::HTTP_PATH().$link;
         if(file_exists($filePath))
         {
            /*
             * Two possible cases: either the upload as its own miniature, either it doesn't. This is 
             * verified with the extension of the file.
             */
            
            $ext = strtolower(substr(strrchr($filePath, '.'), 1));
            if(in_array($ext, Utils::UPLOAD_OPTIONS['miniExtensions']))
            {
               $dimensions = getimagesize($filePath);
               if($dimensions !== FALSE)
               {
                  if(strpos($filePath, 'full_') !== FALSE)
                  {
                     $miniPath = str_replace('full_', 'mini_', $displayPath);
                     $miniHTML = '<img src="'.$miniPath.'" class="miniature" alt="Miniature" ';
                     if($floating !== '')
                     {
                        $miniHTML .= 'style="float: '.$floating.'; ';
                        if($floating === 'left')
                           $miniHTML .= 'margin: 0px 10px 3px 0px;';
                        else
                           $miniHTML .= 'margin: 0px 0px 3px 10px;';
                        $miniHTML .= '" ';
                     }
                     $miniHTML .= 'data-file="'.$displayPath.'" data-width="'.$dimensions[0].'" ';
                     if(strlen($comment) > 0)
                        $miniHTML .= 'data-height="'.$dimensions[1].'" data-comment="'.$comment.'"/>';
                     else
                        $miniHTML .= 'data-height="'.$dimensions[1].'"/>';
                     $parsed = str_replace($miniatures[0][$i], $miniHTML, $parsed);
                  }
               }
            }
            // Short video (WebM or MP4)
            else
            {
               $miniHTML = '<span class="clipThumbnail"';
               if($floating !== '')
               {
                  $miniHTML .= 'style="float: '.$floating.'; ';
                  if($floating === 'left')
                     $miniHTML .= 'margin: 0px 10px 10px 0px;';
                  else
                     $miniHTML .= 'margin: 0px 0px 10px 10px;';
                  $miniHTML .= '" ';
               }
               $miniHTML .= ">\n";
               $miniHTML .= '<video class="miniature" width="250" min-height="10" ';
               if(strlen($comment) > 0)
                  $miniHTML .= "data-file=\"".$displayPath."\" data-comment=\"".$comment."\">\n";
               else
                  $miniHTML .= "data-file=\"".$displayPath."\">\n";
               $miniHTML .= "<source src=\"".$displayPath."\" type=\"video/".$ext."\">\n";
               $miniHTML .= '</video>'."\n";
               $miniHTML .= '<span class="clipThumbnailOverlay"><i class="icon-general_video"></i></span>'."\n";
               $miniHTML .= '</span>'."\n";
               
               $parsed = str_replace($miniatures[0][$i], $miniHTML, $parsed);
            }
         }
      }
      
      // In-article banners to emphasize on some quotes
      $emphasis = array();
      preg_match_all("/\!emphase\[([_a-zA-Z0-9".$accents."\.\\/;:\-]*?)\]\[([_a-zA-Z0-9 ".$accents."\/\.\,:;&'\"\?\!\=\-\+\(\)]*)\]/", $parsed, $emphasis);
      for($i = 0; $i < count($emphasis[1]); $i++)
      {
         $background = $emphasis[1][$i];
         $quote = $emphasis[2][$i];
         
         $isAnURL = self::isURL($background);
         if($isAnURL)
         {
            $relativeLink = self::relativize($background);
            if(strlen($relativeLink) > 0)
               $background = $relativeLink;
            else
               continue;
         }
         
         $filePath = PathHandler::WWW_PATH().$background;
         $displayPath = PathHandler::HTTP_PATH().$background;
         if(file_exists($filePath))
         {
            $backgroundStyle = 'background: url(\''.$displayPath.'\') no-repeat center; background-size: 100%';
         
            $emphasisHTML = "</p>\n<div class=\"emphasis\" style=\"".$backgroundStyle."\">\n";
            $emphasisHTML .= "<div class=\"emphasisWithin\">\n";
            $emphasisHTML .= "<p>\n« ".$quote." »\n</p>\n";
            $emphasisHTML .= "</div>\n</div>\n<p>";
            
            $parsed = str_replace($emphasis[0][$i], $emphasisHTML, $parsed);
         }
      }
      
      // Blocks to emphasize on some text (e.g., conclusion of the article)
      $emphasisBis = array();
      preg_match_all("/\!bloc\[([_a-zA-Z0-9 ".$accents."\/\.\,:;'\"\?\!\=\-\(\)]*)\]\[(.*)\]/Us", $parsed, $emphasisBis);
      
      for($i = 0; $i < count($emphasisBis[1]); $i++)
      {
         $title = $emphasisBis[1][$i];
         $content = $emphasisBis[2][$i];

         $emphasisHTML = "</p>\n<div class=\"emphasisText\">\n";
         $emphasisHTML .= "<h3>".$title."</h3>\n";
         $emphasisHTML .= "<p>".$content;
         $emphasisHTML .= "</p>\n</div>\n<p>";
            
         $parsed = str_replace($emphasisBis[0][$i], $emphasisHTML, $parsed);
      }
      
      // Summary listing strong/weak points of the discussed subject.
      $summaries = array();
      $singleBlock = "([_a-zA-Z0-9 ".$accents."\/\.\,:;'\?\=\-\(\)\!\"]*)";
      preg_match_all("/\!resume\[([^\]]+)\]\[([^\]]+)\]/", $parsed, $summaries);

      for($i = 0; $i < count($summaries[0]); $i++)
      {
         $goodPointsBase = explode(';', $summaries[1][$i]);
         $badPointsBase = explode(';', $summaries[2][$i]);
         
         // Filters lines (removes \n, \r, <br/>, <br> and <br />)
         $goodPoints = array();
         for($j = 0; $j < count($goodPointsBase); $j++)
         {
            if($goodPointsBase[$j] !== '')
            {
               $filtered = str_replace("\n", '', $goodPointsBase[$j]);
               $filtered = str_replace("\r", '', $filtered);
               $filtered = str_replace('<br/>', '', $filtered);
               $filtered = str_replace('<br>', '', $filtered);
               $filtered = str_replace('<br />', '', $filtered);
               if(preg_match("/^".$singleBlock."$/", $filtered))
                  array_push($goodPoints, $filtered);
            }
         }
         
         $badPoints = array();
         for($j = 0; $j < count($badPointsBase); $j++)
         {
            if($badPointsBase[$j] !== '')
            {
               $filtered = str_replace("\n", '', $badPointsBase[$j]);
               $filtered = str_replace("\r", '', $filtered);
               $filtered = str_replace('<br/>', '', $filtered);
               $filtered = str_replace('<br>', '', $filtered);
               $filtered = str_replace('<br />', '', $filtered);
               if(preg_match("/^".$singleBlock."$/", $filtered))
                  array_push($badPoints, $filtered);
            }
         }
         
         if(count($goodPoints) == 0 || count($badPoints) == 0)
            continue;
         
         $summaryHTML = "</p>\n<div class=\"summary\">\n";
         $summaryHTML .= "<div class=\"summaryGood\">\n";
         $summaryHTML .= "<h3>Points forts</h3>\n";
         $summaryHTML .= "<ul>\n";
         for($j = 0; $j < count($goodPoints); $j++)
            $summaryHTML .= "<li>".$goodPoints[$j]."</li>\n";
         $summaryHTML .= "</ul>\n</div>\n";
         $summaryHTML .= "<div class=\"summaryBad\">\n";
         $summaryHTML .= "<h3>Points faibles</h3>\n";
         $summaryHTML .= "<ul>\n";
         for($j = 0; $j < count($badPoints); $j++)
            $summaryHTML .= "<li>".$badPoints[$j]."</li>\n";
         $summaryHTML .= "</ul>\n</div>\n";
         $summaryHTML .= "<div style=\"clear: both;\"></div>\n</div>\n<p>";
         
         $parsed = str_replace($summaries[0][$i], $summaryHTML, $parsed);
      }
      
      // Final step: cleans-up the HTML code from useless tags
      $parsed = preg_replace('(<p>([\s]+)</p>)iUs', '', $parsed);
      $parsed = str_replace("<p><br />\r\n</p>", '', $parsed);
      $parsed = str_replace("<p><br />\n</p>", '', $parsed);
      $parsed = str_replace("<p><br/>", "<p>", $parsed);
      $parsed = str_replace("<p><br />", "<p>", $parsed);
      
      return $parsed;
   }
}

?>
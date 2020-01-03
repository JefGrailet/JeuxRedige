<?php

/**
* This library defines function(s) used to parse a message taken from the DB (which is already 
* parsed, to some extent, in HTML) to treat features that should be easy to maintain and/or which 
* are dependant on the configuration of the page. For example, it replaces !upload[] blocks which 
* can be used to have control over the ratio of the picture or display the corresponding miniature.
*/

class MessageParsing
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
   * Parses an input message to translate format code related to features into HTML.
   *
   * @param string $content  The input message (partially formatted in HTML)
   * @param string $index    The index of the message within a succession of messages (e.g. in a 
   *                         topic or in user's history) to distinct video elements from one post 
   *                         to another; by default it is 0 (i.e. only one message to display)
   * @return string          The same message, fully formatted in HTML
   */

   public static function parse($content, $index = 0)
   {
      $parsed = $content;
      
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
                  
                  if(WebpageHandler::$miscParams['video_default_display'] === 'embedded')
                  {
                     $videoHTML = "<iframe width=\"560\" height=\"315\" src=\"https://www.youtube.com/embed/";
                     $videoHTML .= $IDStr."\" frameborder=\"0\" allowfullscreen></iframe>\n";
                     
                     $parsed = str_replace($videos[0][$i], $videoHTML, $parsed);
                  }
                  else
                  {
                     $iconLink = PathHandler::HTTP_PATH().'/res_icons/';
                     $thumbnailLink = 'http://img.youtube.com/vi/'.$IDStr.'/';
                     
                     if(WebpageHandler::$miscParams['video_thumbnail_style'] === 'hq')
                     {
                        $iconLink .= 'youtube.png';
                        $thumbnailLink .= 'hqdefault.jpg';
                     }
                     else
                     {
                        $iconLink .= 'youtube-small.png';
                        $thumbnailLink .= 'default.jpg';
                     }
                     
                     $videoHTML = "<span class=\"videoWrapper".$index."-".($i + 1)."\">\n";
                     $videoHTML .= "<img class=\"videoThumbnail\" src=\"".$iconLink."\" ";
                     $videoHTML .= "style=\"cursor:pointer; background-image: ";
                     $videoHTML .= "url('".$thumbnailLink."');\"\n";
                     $videoHTML .= "data-video-id=\"".($i + 1)."\" data-post-id=\"".$index."\" ";
                     $videoHTML .= "data-video-true-id=\"".$IDStr."\" data-video-type=\"youtube\" />";
                     $videoHTML .= "</span>\n";
                     
                     $parsed = str_replace($videos[0][$i], $videoHTML, $parsed);
                  }
               }
            }
            
            // TODO (for later): DailyMotion, Vimeo, etc.
         }
      }
      
      // Accepted values for floating
      $acceptedFloating = array('left', 'right');
      
      // Image (full image display) parsing
      $images = array();
      preg_match_all("/\!img\[([_a-zA-Z0-9\.\\/;:\-]*?)\]/", $parsed, $images);
      
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
                        $imageHTML .= 'margin: 5px 10px 5px 0px;';
                     else
                        $imageHTML .= 'margin: 5px 0px 5px 10px;';
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
      preg_match_all("/\!clip\[([_a-zA-Z0-9\.\\/;:\-]*?)\]/", $parsed, $clips);
      
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
                  $clipHTML .= 'margin: 5px 10px 5px 0px;';
               else
                  $clipHTML .= 'margin: 5px 0px 5px 10px;';
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
      $accents = "áàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ";
      preg_match_all("/\!mini\[([_a-zA-Z0-9\.\\/;:\-]*?)\](\[([a-zA-Z0-9 ".$accents."\.\,:;'\?\!\=\-\(\)\/]*)\])?/", $parsed, $miniatures);
      
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
                           $miniHTML .= 'margin: 5px 10px 5px 0px;';
                        else
                           $miniHTML .= 'margin: 5px 0px 5px 10px;';
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
               $miniHTML = '<video class="miniature" width="250" min-height="10" ';
               if($floating !== '')
               {
                  $miniHTML .= 'style="float: '.$floating.'; ';
                  if($floating === 'left')
                     $miniHTML .= 'margin: 5px 10px 5px 0px;';
                  else
                     $miniHTML .= 'margin: 5px 0px 5px 10px;';
                  $miniHTML .= '" ';
               }
               if(strlen($comment) > 0)
                  $miniHTML .= "data-file=\"".$displayPath."\" data-comment=\"".$comment."\">\n";
               else
                  $miniHTML .= "data-file=\"".$displayPath."\">\n";
               $miniHTML .= "<source src=\"".$displayPath."\" type=\"video/".$ext."\">\n";
               $miniHTML .= '</video>';
               
               $parsed = str_replace($miniatures[0][$i], $miniHTML, $parsed);
            }
         }
      }
      
      // Users (pretty display)
      $usersPretty = array();
      preg_match_all("/\!user\[([a-zA-Z0-9_-]{3,20})\]/", $parsed, $usersPretty);
      
      for($i = 0; $i < count($usersPretty[1]); $i++)
      {
         $userPseudo = $usersPretty[1][$i];
         $userHTML = '<img src="'.PathHandler::getAvatarSmall($userPseudo).'" alt="'.$userPseudo.'" class="userMiniAvatar" /> ';
         $userHTML .= '<span class="userMiniPseudo">'.$userPseudo.'</span>';
         
         $parsed = str_replace($usersPretty[0][$i], $userHTML, $parsed);
      }
      
      // Final step: cleans-up the HTML code from useless tags
      $parsed = preg_replace('(<p>([\s]+)</p>)iUs', '', $parsed);
      $parsed = str_replace("<p><br />\r\n</p>", '', $parsed);
      $parsed = str_replace("<p><br />\n</p>", '', $parsed);
      $parsed = str_replace("<p><br/>", "<p>", $parsed);
      $parsed = str_replace("<p><br />", "<p>", $parsed);
      
      return $parsed;
   }

   /*
   * Parses an input message to replace format code related to features by empty strings, as a 
   * method of censorship.
   *
   * @param string $content  The input message (partially formatted in HTML)
   * @return string          The same message, fully formatted in HTML
   */

   public static function parseCensored($content)
   {
      $parsed = $content;
      
      // Videos
      $videos = array();
      preg_match_all("/\!video\[([_a-zA-Z0-9\.\\/;:\?\=\-]*?)\]/", $parsed, $videos);
      
      for($i = 0; $i < count($videos[1]); $i++)
         $parsed = str_replace($videos[0][$i], '', $parsed);
      
      // Image (full image display) parsing
      $images = array();
      preg_match_all("/\!img\[([_a-zA-Z0-9\.\\/;:\-]*?)\]/", $parsed, $images);
      
      for($i = 0; $i < count($images[1]); $i++)
         $parsed = str_replace($images[0][$i], '', $parsed);
      
      // Images which can be opened in the lightbox (in addition with regular display)
      $miniatures = array();
      preg_match_all("/\!mini\[([_a-zA-Z0-9\.\\/;:\-]*?)\]/", $parsed, $miniatures);
      
      for($i = 0; $i < count($miniatures[1]); $i++)
         $parsed = str_replace($miniatures[0][$i], '', $parsed);
      
      return $parsed;
   }

   /*
   * Parses an input message to take care of references to other posts. This is only relevant in 
   * topics or private threads, but not in out-of-context messages such as what can be seen in one 
   * user's post history.
   *
   * @param string $content  The input message (partially formatted in HTML)
   * @param string $URL      The URL to the topic, where the page number is replaced with []
   * @return string          The same message, fully formatted in HTML
   */

   public static function parseReferences($content, $URL)
   {
      $parsed = $content;

      $references = array();
      preg_match_all("/\!ref\[([_a-zA-Z0-9\.\\/;:\?=\-' ]*?)\]/", $parsed, $references);
      
      for($i = 0; $i < count($references[1]); $i++)
      {
         $idPost = 0;
         $name = '';
         if(strpos($references[1][$i], ';') !== FALSE)
         {
            $exploded = explode(';', $references[1][$i]);
            $idPost = intval($exploded[0]);
            $name = $exploded[1];
         }
         else if(!is_numeric($references[1][$i]))
         {
            continue;
         }
         else
         {
            $idPost = intval($references[1][$i]);
            $name = $references[1][$i];
         }
         
         // TODO: move to post via Jquery
         
         $nbPage = ceil($idPost / WebpageHandler::$miscParams['posts_per_page']);
         $refHTML = "<a href=\"".str_replace('[]', $nbPage, $URL)."#".$idPost."\">@".$name."</a>";
         $parsed = str_replace($references[0][$i], $refHTML, $parsed);
      }
      
      // TODO: parse !user[] tags (display a user with mini-avatar)
      
      return $parsed;
   }

   /*
   * Parses an input message to remove references. Meant for out-of-context display.
   *
   * @param string $content  The input message (partially formatted in HTML)
   * @return string          The same message, fully formatted in HTML
   */

   public static function removeReferences($content)
   {
      $parsed = $content;
      
      $references = array();
      preg_match_all("/\!ref\[([_a-zA-Z0-9\.\\/\;\:\?\=\-' ]*?)\]/", $parsed, $references);
      
      for($i = 0; $i < count($references[1]); $i++)
      {
         $name = '';
         if(strpos($references[1][$i], ';') !== FALSE)
         {
            $exploded = explode(';', $references[1][$i]);
            $name = $exploded[1];
         }
         else if(!is_numeric($references[1][$i]))
         {
            continue;
         }
         else
         {
            $name = $references[1][$i];
         }
         
         $refHTML = "<span style=\"color: grey;\">@".$name."</span>";
         $parsed = str_replace($references[0][$i], $refHTML, $parsed);
      }
      
      return $parsed;
   }
}

?>
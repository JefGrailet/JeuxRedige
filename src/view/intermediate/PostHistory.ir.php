<?php

class PostHistoryIR
{
   /*
   * Converts the array modelizing an archived post into an intermediate representation, ready to 
   * be used in a template. The intermediate representation is a new array containing:
   *
   * -Version number
   * -Pseudonym, avatar and style of <h1> (can be empty) of the author when he posted this post
   * -Date of the post as a text
   * -The censorship part (HTML)
   * -The content (HTML, some additions regarding what is stored in the DB)
   * -The upload part, if there are uploads (formatted as yes||postID|display|uploadsHTML)
   *
   * @param mixed $post[]      The archived post (via method getAll() from PostHistory class)
   * @return mixed[]           The intermediate representation
   */

   public static function process($post)
   {
      $output = array('versionNumber' => $post['version'],
      'authorPseudo' => $post['author'],
      'authorAvatar' => PathHandler::HTTP_PATH().'defaultavatar.jpg',
      'authorStyle' => '',
      'editor' => $post['editor'], 
      'date' => 'Le '.date('d/m/Y à H:i:s', Utils::toTimestamp($post['date'])),
      'censorship' => '',
      'content' => '',
      'showAttachment' => '');
      
      // Avatar, style of the <h1> displaying the author's pseudonym
      if($post['posted_as'] !== 'anonymous')
         $output['authorAvatar'] = PathHandler::getAvatar($post['author']);
      
      if($post['posted_as'] === 'anonymous')
         $output['authorStyle'] = ' style="background-color: #557083;" title="Anonyme"';
      else if($post['posted_as'] === 'administrator')
         $output['authorStyle'] = ' style="background-color: #910017;" title="Administrateur"';

      // Censorship part
      $interactive = false;
      if(LoggedUser::isLoggedIn() && LoggedUser::$data['used_pseudo'] === LoggedUser::$data['function_pseudo'] && Utils::check(LoggedUser::$data['can_edit_all_posts']))
         $interactive = true;
      
      $censorshipPart = '';
      if($interactive)
      {
         if($post['editor'] === LoggedUser::$data['pseudo'] || $post['editor'] === LoggedUser::$data['used_pseudo'])
            $censorshipPart = '<i class="censorship icon-general_alert" style="opacity: 0.2; cursor: default;" title="Non censuré"></i>';
         // Not censored yet
         else if(!Utils::check($post['censorship']))
            $censorshipPart = ' <i class="censorship icon-general_alert" style="opacity: 0.5; cursor: pointer;" data-id-post="'.$post['id_post'].'" data-version="'.$post['version'].'" title="Censurer"/></i>';
         // Already censored
         else
            $censorshipPart = '<i class="censorship icon-general_alert" style="opacity: 1.0; cursor: default;" title="Censuré"></i>';
      }
      else
      {
         if(Utils::check($post['censorship']))
            $censorshipPart = '<i class="censorship icon-general_alert" style="opacity: 1.0; cursor: default;" title="Censuré"></i>';
         else
            $censorshipPart = '<i class="censorship icon-general_alert" style="opacity: 0.2; cursor: default;" title="Non censuré"></i>';
      }
      
      $output['censorship'] = $censorshipPart;
      
      // Message is masked if censored by an admin
      if(Utils::check($post['censorship']))
      {
         $output['content'] = '<p>
         <span style="color: grey;">Cette version du message a été censurée par un modérateur. Par 
         conséquent, son contenu a été masqué à titre préventif.<br/>
         <br/>
         <a href="javascript:void(0)" class="link_masked_post" data-id-post="'.$post['version'].'">
         Cliquez ici</a> pour afficher/masquer ce contenu.</span>
         </p>
         <div id="masked'.$post['version'].'" style="display: none;">
         <p>
         '.$post['content'].'
         </p>
         </div>';
      }
      else
      {
         $output['content'] = '<p>
         '.$post['content'].'
         </p>';
      }
      
      // Strips empty <p></p> HTML tags
      $output['content'] = preg_replace('/(<p>([\s]+)<\/p>)/iU', '', $output['content']);
      
      // Attachment part (the parsing is currently done here)
      if(strlen($post['attachment']) > 0)
      {
         $posColon = strpos($post['attachment'], ':');
         if($posColon !== FALSE)
         {
            $attachPrefix = substr($post['attachment'], 0, $posColon);
            $attachContent = substr($post['attachment'], $posColon + 1);
            
            // Parses the display policy (if any)
            $displayPolicy = '';
            if(strpos($attachPrefix, "_") !== FALSE)
            {
               $prefixSplitted = explode('_', $attachPrefix);
               $attachPrefix = $prefixSplitted[0];
               $displayPolicy = $prefixSplitted[1];
            }
            
            if($attachPrefix === 'uploads' && $displayPolicy !== 'noshow')
            {
               $uploadsArr = explode(',', $attachContent);
               $httpPathPrefix = PathHandler::HTTP_PATH().'upload/topics/'.$post['id_topic'].'/';
               $filePathPrefix = PathHandler::WWW_PATH().'upload/topics/'.$post['id_topic'].'/';
               $httpPathPrefix .= $post['id_post'].'_';
               $filePathPrefix .= $post['id_post'].'_';
               
               $uploadDisplay = '';
               $IDInGallery = 0;
               $fullInput = array();
               for($i = 0; $i < count($uploadsArr); $i++)
               {
                  $filePath = $filePathPrefix.$uploadsArr[$i];
                  if(file_exists($filePath))
                  {
                     $extension = strtolower(substr(strrchr($uploadsArr[$i], '.'), 1));
                     if(in_array($extension, Utils::UPLOAD_OPTIONS['miniExtensions']))
                     {
                        $IDInGallery++;
                     
                        $explodedUpload = explode('_', $uploadsArr[$i], 2);
                        $uploader = $explodedUpload[0];
                        $miniature = $httpPathPrefix.$uploader.'_mini_'.substr($explodedUpload[1], 5);
                        $dimensions = getimagesize($filePath);
                        
                        $tplInput = array('fullSize' => $httpPathPrefix.$uploadsArr[$i],
                        'width' => $dimensions[0],
                        'height' => $dimensions[1],
                        'uploader' => $uploader,
                        'uploadDate' => date('d/m/Y à H:i:s', filemtime($filePath)),
                        'postID' => $post['id_post'],
                        'pictureID' => $IDInGallery,
                        'slideshowPrevious' => '',
                        'slideshowNext' => '',
                        'miniature' => $miniature);
                        
                        array_push($fullInput, $tplInput);
                     }
                     else
                     {
                        // Done later
                     }
                  }
               }
               
               if(count($fullInput) > 0)
               {
                  $tplOutput = TemplateEngine::parseMultiple('view/content/Upload.item.display.ctpl', $fullInput);
                  
                  if(!TemplateEngine::hasFailed($tplOutput))
                  {
                     $first = true;
                     for($i = 0; $i < count($tplOutput); $i++)
                     {
                        if($first)
                           $first = false;
                        else
                           $uploadDisplay .= ' ';
                        $uploadDisplay .= $tplOutput[$i];
                     }
                  }
               }
               
               if(strlen($uploadDisplay) > 0)
               {
                  $showingAttachment = 'yes||'.$post['version'].'|';
                  if(Utils::check($post['censorship']) || strlen($displayPolicy) > 0)
                  {
                     $showingAttachment .= '<p><a href="javascript:void(0)" class="link_masked_attachment" 
                     data-id-post="'.$post['version'].'">Cliquez ici</a> 
                     pour afficher/masquer les uploads liés à ce message';
                     
                     if(Utils::check($post['censorship']))
                        $showingAttachment .= ' (<strong>censuré</strong>)';
                     else if($displayPolicy === 'spoiler' || $displayPolicy === 'noshowspoiler')
                        $showingAttachment .= ' (<strong>spoilers</strong>)';
                     else if($displayPolicy === 'nsfw' || $displayPolicy === 'noshownsfw')
                        $showingAttachment .= ' (<strong>contenu mature</strong>)';
                     
                     $showingAttachment .= '.</span></p>'."\n";
                     $showingAttachment .= '<div id="maskedAttachment'.$post['id_post'].'" style="display: none;">'."\n";
                     $showingAttachment .= $uploadDisplay;
                     $showingAttachment .= '</div>'."\n";
                  }
                  else
                  {
                     $showingAttachment .= $uploadDisplay."\n";
                  }
                  
                  $output['showAttachment'] = $showingAttachment;
               }
            }
         }
      }
      
      return $output;
   }
}

?>

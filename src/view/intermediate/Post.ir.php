<?php

class PostIR
{
   /*
   * Converts the array modelizing a post into an intermediate representation, ready to be used in 
   * a template. The intermediate representation is a new array containing:
   *
   * -ID of the post
   * -Its index in its parent topic
   * -Top left part of the post (to display: index of the post, associated title, etc.)
   * -Pseudonym, avatar and style (can be empty) of the author when (s)he posted this post
   * -String telling what kind of author wrote the message ("user", "anon", "admin" or "auto")
   * -Date of the post as a text
   * -Edition link for this post (HTML or empty)
   * -Quote link for this post (HTML or empty)
   * -The scoring part (HTML)
   * -The report part (HTML)
   * -The content (HTML, some additions regarding what is stored in the DB)
   * -The upload part, if uploads are attached (formatted as yes||postID|display|uploadsHTML)
   *
   * @param mixed $post[]      The post itself (obtained with method getAll() from Post class)
   * @param number $index      Index of the post in its parent topic (<= 0 if out of context)
   * @param bool $interactive  To set to false to remove interactivity (e.g., scoring)
   * @return mixed[]           The intermediate representation
   */

   public static function process($post, $index, $interactive = true)
   {
      $webRootPath = PathHandler::HTTP_PATH();
      
      // Special case: if the post is automatic, $interactive is reset to false
      if($post['posted_as'] === 'author')
         $interactive = false;
      
      $output = array('ID' => $post['id_post'], 
      'index' => '', 
      'topLeftPart' => '', 
      'authorPseudo' => $post['author'], 
      'authorAvatar' => $webRootPath.'defaultavatar.jpg', 
      'authorStyle' => '',
      'authorType' => '',
      'date' => Utils::printDate($post['date']), 
      'editionLink' => '', 
      'quoteLink' => '', 
      'pinButton' => '',
      'score' => '', 
      'report' => '', 
      'content' => '', 
      'showAttachment' => '');
      
      // Icons
      $icons = NULL;
      if(WebpageHandler::$miscParams['message_size'] === 'medium')
      {
         $icons = array('permalink' => $webRootPath.'res_icons/post_permalink_med.png', 
         'edit' => $webRootPath.'res_icons/post_edit_med.png', 
         'quote' => $webRootPath.'res_icons/post_quote_med.png', 
         'info' => $webRootPath.'res_icons/post_info_med.png', 
         'alert' => $webRootPath.'res_icons/post_alert_med.png', 
         'thumb_up' => $webRootPath.'res_icons/post_thumb_up_med.png', 
         'thumb_down' => $webRootPath.'res_icons/post_thumb_down_med.png', 
         'pin' => $webRootPath.'res_icons/post_pin_med.png', 
         'unpin' => $webRootPath.'res_icons/post_unpin_med.png');
      }
      else
      {
         $icons = array('permalink' => $webRootPath.'res_icons/post_permalink.png', 
         'edit' => $webRootPath.'res_icons/post_edit.png', 
         'quote' => $webRootPath.'res_icons/post_quote.png', 
         'info' => $webRootPath.'res_icons/post_info.png', 
         'alert' => $webRootPath.'res_icons/post_alert.png', 
         'thumb_up' => $webRootPath.'res_icons/post_thumb_up.png', 
         'thumb_down' => $webRootPath.'res_icons/post_thumb_down.png', 
         'pin' => $webRootPath.'res_icons/post_pin.png', 
         'unpin' => $webRootPath.'res_icons/post_unpin.png');
      }
      
      // Top left part: index of the post in the parent topic or link to replace in context
      if($index <= 0)
      {
         $output['index'] = $post['id_post'];
         $output['topLeftPart'] = '<a href="'.$webRootPath.'Context.php?id_post='.$post['id_post'].'" target="_blank">Contexte</a>';
      }
      else
      {
         $output['index'] = $index;
         $output['topLeftPart'] = '<strong>#'.$index.'</strong>';
      }
      
      // Avatar, style of the author's pseudonym and online status
      if($post['posted_as'] !== 'anonymous')
         $output['authorAvatar'] = PathHandler::getAvatar($post['author']);
      
      if($post['posted_as'] === 'anonymous')
      {
         $output['authorStyle'] = ' style="background-color: #557083;" title="Anonyme"';
         $output['authorType'] = 'anon';
      }
      else if($post['posted_as'] === 'administrator')
      {
         if(array_key_exists('online', $post) && $post['online'])
            $output['authorStyle'] = ' style="background-color: #DD0017;" title="Administrateur, en ligne"';
         else
            $output['authorStyle'] = ' style="background-color: #910017;" title="Administrateur"';
         $output['authorType'] = 'admin';
      }
      else
      {
         if(array_key_exists('online', $post) && $post['online'])
            $output['authorStyle'] = ' style="background-color: #38883f;" title="En ligne"';
         $output['authorType'] = 'user';
      }
      
      // If author is not anonymous, a link to User.php with his/her history is added
      if($post['posted_as'] !== 'anonymous' && $post['posted_as'] !== 'administrator')
      {
         $output['authorPseudo'] = '<a href="'.PathHandler::userURL($post['author']).'" target="_blank" class="authorPseudo">'.$post['author'].'</a>';
      }
      
      // Permanent link (or permalink)
      $output['permalink'] = ' &nbsp;<a href="'.$webRootPath.'Permalink.php?id_post='.$post['id_post'].'" target="_blank"><img class="postIcon"';
      $output['permalink'] .= ' src="'.$icons['permalink'].'" alt="Lien permanent" title="Lien permanent"/></a>';
      
      // Edition link
      $output['editionLink'] = '';
      
      if($interactive && LoggedUser::isLoggedIn() && (Utils::check(LoggedUser::$data['can_edit_all_posts']) OR (($post['author'] === LoggedUser::$data['pseudo']
         || $post['author'] === LoggedUser::$data['used_pseudo']) && $post['posted_as'] !== 'anonymous')))
      {
         $output['editionLink'] .= ' &nbsp;<a href="'.$webRootPath.'EditMessage.php?id_post='.$post['id_post'].'"><img class="postIcon"';
         $output['editionLink'] .= ' src="'.$icons['edit'].'" alt="Editer" title="Editer"/></a>';
      }
      
      // Quote link and pin/unpin buttons
      if($interactive && LoggedUser::isLoggedIn() && $post['author'] !== LoggedUser::$data['pseudo'] && 
         $post['author'] !== LoggedUser::$data['used_pseudo'])
      {
         $output['editionLink'] .= ' &nbsp;<img class="quote" data-post="'.$post['id_post'].'" ';
         $output['editionLink'] .= 'src="'.$icons['quote'].'" alt="Citer" title="Citer"/>';
         
         if(strlen($post['user_pin']) > 0)
         {
            $output['pinButton'] .= ' &nbsp;<img class="pin" data-post="'.$post['id_post'].'" src="'.$icons['unpin'].'" ';
            $output['pinButton'] .= 'alt="Retirer de mes favoris" title="'.$post['user_pin'].'"/></a>';
         }
         else
         {
            $output['pinButton'] .= ' &nbsp;<img class="pin" data-post="'.$post['id_post'].'" src="'.$icons['pin'].'" ';
            $output['pinButton'] .= 'alt="Ajouter ce message à mes favoris" title="Ajouter ce message à mes favoris"/></a>';
         }
      }
      
      // Scoring part of the post
      $scorePart = '';
      $finalScore = $post['nb_likes'] - $post['nb_dislikes'];
      
      $whoLikes = ''.$post['nb_likes'].' J\'aime, '.$post['nb_dislikes'].' Je n\'aime pas';
      $whoLikes = ' <img class="postInteractions" data-post="'.$post['id_post'].'" data-likes="'.$post['nb_likes'].'" 
                   data-dislikes="'.$post['nb_dislikes'].'" src="'.$icons['info'].'" alt="'.$whoLikes.'" title="'.$whoLikes.'">';
      
      // The way like/dislike buttons are displayed depends on whether the user is logged and/or voted
      if($interactive && LoggedUser::isLoggedIn() && (($post['author'] !== LoggedUser::$data['pseudo'] && $post['author'] !== LoggedUser::$data['used_pseudo']
         && $post['author'] !== LoggedUser::$data['function_pseudo']) || $post['posted_as'] === 'anonymous'))
      {
         $opacityLike = '';
         $opacityDislike = '';
         $vote = $post['user_vote'];
         if($vote < 0)
            $opacityLike = ' style="opacity: 0.2;"';
         else if($vote > 0)
            $opacityDislike = ' style="opacity: 0.2;"';
         
         // Useful note: user_vote does not have to exist in the array if $interactive is set to false.
         
         if($finalScore > 0)
            $scorePart = '<span style="color: green;" class="votes" id="score'.$index.'" data-score="'.$finalScore.'" data-post="'.$post['id_post'].'" data-has-voted="'.$vote.'">+'.$finalScore.'</span>&nbsp; ';
         else if($finalScore == 0)
            $scorePart = '<span style="color: green;" class="votes" id="score'.$index.'" data-score="0" data-post="'.$post['id_post'].'" data-has-voted="'.$vote.'">0</span>&nbsp; ';
         else
            $scorePart = '<span style="color: red;" class="votes" id="score'.$index.'" data-score="'.$finalScore.'" data-post="'.$post['id_post'].'" data-has-voted="'.$vote.'">'.$finalScore.'</span>&nbsp; ';
         $scorePart .= $whoLikes.'&nbsp;';
         
         $scorePart .= ' 
         <img class="vote" data-post="'.$post['id_post'].'"'.$opacityLike.' data-vote="1" src="'.$icons['thumb_up'].'" alt="J\'aime" title="J\'aime"/> 
         <img class="vote" data-post="'.$post['id_post'].'"'.$opacityDislike.' data-vote="-1" src="'.$icons['thumb_down'].'" alt="Je n\'aime pas" title="Je n\'aime pas"/>
         ';
      }
      // If the user is not logged or is the author of this post, we just display the score.
      else
      {
         if($finalScore > 0)
            $scorePart = '<span style="color: green;" id="score'.$index.'" data-score="'.$finalScore.'" >+'.$finalScore.'</span>&nbsp; ';
         else if($finalScore == 0)
            $scorePart = '<span style="color: green;" id="score'.$index.'" data-score="0" >0</span>&nbsp; ';
         else
            $scorePart = '<span style="color: red;" id="score'.$index.'" data-score="'.$finalScore.'" >'.$finalScore.'</span>&nbsp; ';
         $scorePart .= $whoLikes;
      }
      
      $output['score'] = $scorePart;
      
      // Report part of the post
      if($interactive && LoggedUser::isLoggedIn() && LoggedUser::$data['pseudo'] !== $post['author'] && LoggedUser::$data['function_pseudo'] !== $post['author'])
      {
         // Not reported yet
         if($post['user_alert'] === 'no')
         {
            $output['report'] = ' <img class="report" style="opacity: 0.5;" data-post="'.$post['id_post'].'"
            src="'.$icons['alert'].'" alt="Emettre une alerte" title="Emettre une alerte"/> ';
         }
         // Already reported
         else
         {
            $alertDetails = 'Vous avez émis une alerte ('.$post['user_alert'].')';
            $output['report'] = '<img class="reported" src="'.$icons['alert'].'"
            style="opacity: 1.0; cursor: default;" alt="'.$alertDetails.'" title="'.$alertDetails.'">';
         }
      }
      
      // Finally, we prepare the content itself
      $displayModifications = '';
      if($post['nb_edits'] > 0)
      {
         $lastModificationDate = date('d/m/Y à H:i:s', Utils::toTimestamp($post['last_edit']));
         $displayModifications = '<p style="color: grey;">Message édité '.$post['nb_edits'].' ';
         $displayModifications .= 'fois; dernière édition le '.$lastModificationDate.' par ';
         if($post['last_editor'] !== $post['author'])
            $displayModifications .= '<strong>'.$post['last_editor'].'</strong>';
         else
            $displayModifications .= $post['last_editor'];
         $displayModifications .= ' (<a href="'.$webRootPath.'PostHistory.php?id_post='.$post['id_post'];
         $displayModifications .= '" target="_blank">historique complète</a>).</p>';
      }
      
      // If content is ending with a div, do not end with "</p>"
      $postEnd = '</p>';
      if(substr($post['content'], -8) === "</div>\r\n")
         $postEnd = '';
      
      // Message is masked if reported too many times or reported by an admin
      if($post['bad_score'] >= 10)
      {
         $output['content'] = '<p>
         <span style="color: grey;">Ce message a été signalé par plusieurs inscrits et/ou
         un modérateur comme inapproprié/offensant. Par conséquent, son contenu a été masqué à titre
         préventif.<br/>
         <br/>
         <a href="javascript:void(0)" class="link_masked_post" data-id-post="'.$post['id_post'].'">
         Cliquez ici</a> pour afficher/masquer ce contenu.</span>
         </p>
         <div id="masked'.$post['id_post'].'" style="display: none;">
         <p>
         '.$post['content'].'
         '.$postEnd.'
         '.$displayModifications.'
         </div>';
      }
      else
      {
         $output['content'] = '<p>
         '.$post['content'].'
         '.$postEnd.'
         '.$displayModifications;
      }
      
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
            $hiddenPolicies = array('noshow', 'noshownsfw', 'noshowspoiler');
            if(strpos($attachPrefix, "_") !== FALSE)
            {
               $prefixSplitted = explode('_', $attachPrefix);
               $attachPrefix = $prefixSplitted[0];
               $displayPolicy = $prefixSplitted[1];
            }
            
            if($attachPrefix === 'uploads' && !in_array($displayPolicy, $hiddenPolicies))
            {
               $uploadsArr = explode(',', $attachContent);
               $httpPathPrefix = $webRootPath.'upload/topics/'.$post['id_topic'].'/';
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
                        'dimensions' => 'yes||'.$dimensions[0].'|'.$dimensions[1],
                        'uploader' => $uploader,
                        'uploadDate' => date('d/m/Y à H:i:s', filemtime($filePath)),
                        'postID' => $post['id_post'],
                        'itemID' => $IDInGallery,
                        'slideshowPrevious' => '',
                        'slideshowNext' => '',
                        'content' => 'picture||'.$miniature);
                        
                        array_push($fullInput, $tplInput);
                     }
                     else
                     {
                        $IDInGallery++;
                        
                        $explodedUpload = explode('_', $uploadsArr[$i], 2);
                        $uploader = $explodedUpload[0];
                        
                        $tplInput = array('fullSize' => $httpPathPrefix.$uploadsArr[$i],
                        'dimensions' => '',
                        'uploader' => $uploader,
                        'uploadDate' => date('d/m/Y à H:i:s', filemtime($filePath)),
                        'postID' => $post['id_post'],
                        'itemID' => $IDInGallery,
                        'slideshowPrevious' => '',
                        'slideshowNext' => '',
                        'content' => 'video||'.$httpPathPrefix.$uploadsArr[$i].'|'.$extension);
                        
                        array_push($fullInput, $tplInput);
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
                  $showingAttachment = 'yes||'.$post['id_post'].'|';
                  if($post['bad_score'] >= 10 || strlen($displayPolicy) > 0)
                  {
                     $showingAttachment .= '<p><a href="javascript:void(0)" class="link_masked_attachment" 
                     data-id-post="'.$post['id_post'].'">Cliquez ici</a> 
                     pour afficher/masquer les uploads liés à ce message';
                     
                     if($post['bad_score'] >= 10)
                        $showingAttachment .= ' (<strong>censuré</strong>)';
                     else if($displayPolicy === 'spoiler')
                        $showingAttachment .= ' (<strong>spoilers</strong>)';
                     else if($displayPolicy === 'nsfw')
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

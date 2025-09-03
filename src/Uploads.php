<?php

/**
* This script displays all uploads from a topic, in addition with its header (plus games, if any).
*/

require './libraries/Header.lib.php';

require './view/intermediate/TopicHeader.ir.php';
require './model/Topic.class.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);
   
   // Prepares the input for the topic template
   $finalTplInput = array('header' => '',
   'topicLink' => '',
   'content' => '');

   // Obtains topic, related data and uploads
   try
   {
      $topic = new Topic($getID);
      $topic->loadMetadata();
      $nbPosts = $topic->countPosts();
      $attachments = $topic->listAttachments();
   }
   // Handles exceptions
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';
      $tpl = TemplateEngine::parse('view/content/Topic.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet introuvable');
   }
   
   $finalTplInput['topicLink'] = PathHandler::topicURL($topic->getAll());
   
   // Webpage settings
   WebpageHandler::addCSS('topic');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('topic_medium');
   WebpageHandler::addCSS('topic_header');
   if($topic->hasGames())
      WebpageHandler::addCSS('media');
   WebpageHandler::addJS('topic_interaction');
   WebpageHandler::changeContainer('topicContent');
   
   // Dialog boxes for the moderator (lock, unlock and delete)
   $dialogs = '';
   if(LoggedUser::isLoggedIn())
   {
      if(Utils::check(LoggedUser::$data['can_lock']))
      {
         $tplInput = array('topicID' => $getID, 'lockStatus' => 'unlocked');
         if(Utils::check($topic->get('is_locked')))
            $tplInput['lockStatus'] = 'locked';
         $dialogTpl = TemplateEngine::parse('view/dialog/LockTopic.dialog.ctpl', $tplInput);
         if(!TemplateEngine::hasFailed($dialogTpl))
            $dialogs .= $dialogTpl;
      }
      if(Utils::check(LoggedUser::$data['can_delete']))
      {
         $dialogTpl = TemplateEngine::parse('view/dialog/DeleteTopic.dialog.ctpl', array('topicID' => $getID));
         if(!TemplateEngine::hasFailed($dialogTpl))
            $dialogs .= $dialogTpl;
      }
   }
   
   // Topic header
   $headerTplInput = TopicHeaderIR::process($topic, 'uploads');
   $headerTpl = TemplateEngine::parse('view/content/TopicHeader.ctpl', $headerTplInput);
   if(!TemplateEngine::hasFailed($headerTpl))
      $finalTplInput['header'] = $headerTpl;
   else
      WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du sujet');
   
   // Deals with uploads
   $uploadsList = '';
   $IDInGallery = 0;
   if($attachments != NULL)
   {
      $fullInput = array();
      for($i = 0; $i < count($attachments); $i++)
      {
         // Skip this post if it has a high enough "bad_score"
         if($attachments[$i]['bad_score'] >= 10)
            continue;
      
         // N.B.: a post can have several attachments, but only an "uploads" one
         $exploded = explode('|', $attachments[$i]['attachment']);
         $iHasUploads = false;
         $uploadsAttachment = '';
         for($j = 0; $j < count($exploded); $j++)
         {
            if(substr($exploded[$j], 0, 7) === 'uploads')
            {
               $iHasUploads = true;
               $uploadsAttachment = $exploded[$j];
               break;
            }
         }
         
         // Next iteration if this post has no uploads
         if(!$iHasUploads)
            continue;
            
         $posColon = strpos($uploadsAttachment, ':');
         if($posColon !== FALSE)
         {
            $attachContent = substr($uploadsAttachment, $posColon + 1);
            $uploadsPrefix = substr($uploadsAttachment, 0, $posColon);
            $showPolicy = '';
            if(strpos($uploadsPrefix, '_') !== FALSE)
            {
               $explodedPrefix = explode('_', $uploadsPrefix);
               $showPolicy = $explodedPrefix[1];
            }
            
            // Now, a bit of parsing.
            $uploadsArr = explode(',', $attachContent);
            $httpPathPrefix = PathHandler::HTTP_PATH().'upload/topics/'.$topic->get('id_topic').'/';
            $filePathPrefix = PathHandler::WWW_PATH().'upload/topics/'.$topic->get('id_topic').'/';
            $httpPathPrefix .= $attachments[$i]['id_post'].'_';
            $filePathPrefix .= $attachments[$i]['id_post'].'_';
            
            $uploadDisplay = '';
            for($k = 0; $k < count($uploadsArr); $k++)
            {
               $filePath = $filePathPrefix.$uploadsArr[$k];
               if(file_exists($filePath))
               {
                  $extension = strtolower(substr(strrchr($uploadsArr[$k], '.'), 1));
                  
                  /*
                  * To have continuous slideshow, giving the ID of the previous/next post with 
                  * uploads at the beginning/end of the current list of uploads is necessary.
                  */
                  
                  $slideshowPreviousPost = '';
                  if($k == 0)
                  {
                     if($i >= 0)
                     {
                        $previousPostID = 0;
                        for($l = $i - 1; $l >= 0; $l--)
                        {
                           if($attachments[$l]['bad_score'] < 10)
                           {
                              $previousPostID = $attachments[$l]['id_post'];
                              break;
                           }
                        }
                        
                        if($previousPostID > 0)
                           $slideshowPreviousPost = 'yes||'.$previousPostID;
                     }
                  }
                  
                  $slideshowNextPost = '';
                  if($k == (count($uploadsArr) - 1))
                  {
                     if($i < (count($attachments) - 1))
                     {
                        $nextPostID = 0;
                        for($l = $i + 1; $l < count($attachments); $l++)
                        {
                           if($attachments[$l]['bad_score'] < 10)
                           {
                              $nextPostID = $attachments[$l]['id_post'];
                              break;
                           }
                        }
                        
                        if($nextPostID > 0)
                           $slideshowNextPost = 'yes||'.$nextPostID;
                     }
                  }
                     
                  if(in_array($extension, Utils::UPLOAD_OPTIONS['miniExtensions']))
                  {
                     $IDInGallery++;
                  
                     $explodedUpload = explode('_', $uploadsArr[$k], 2);
                     $uploader = $explodedUpload[0];
                     $previewContent = '';
                     if(strlen($showPolicy) > 0)
                     {
                        if($showPolicy === 'nsfw' || $showPolicy === 'noshownsfw')
                           $previewContent = 'nsfw';
                        else if($showPolicy === 'spoiler' || $showPolicy === 'noshowspoiler')
                           $previewContent = 'spoiler';
                        else
                           $previewContent = 'picture||'.$httpPathPrefix.$uploader.'_mini_'.substr($explodedUpload[1], 5);
                     }
                     else
                        $previewContent = 'picture||'.$httpPathPrefix.$uploader.'_mini_'.substr($explodedUpload[1], 5);
                     $dimensions = getimagesize($filePath);
                     
                     $tplInput = array('fullSize' => $httpPathPrefix.$uploadsArr[$k],
                     'dimensions' => 'yes||'.$dimensions[0].'|'.$dimensions[1],
                     'uploader' => $uploader,
                     'uploadDate' => date('d/m/Y à H:i:s', filemtime($filePath)),
                     'postID' => $attachments[$i]['id_post'],
                     'itemID' => $IDInGallery,
                     'slideshowPrevious' => $slideshowPreviousPost,
                     'slideshowNext' => $slideshowNextPost,
                     'content' => $previewContent);
                     
                     array_push($fullInput, $tplInput);
                  }
                  else
                  {
                     $IDInGallery++;
                     
                     $explodedUpload = explode('_', $uploadsArr[$k], 2);
                     $uploader = $explodedUpload[0];
                     $previewContent = '';
                     if(strlen($showPolicy) > 0)
                     {
                        if($showPolicy === 'nsfw' || $showPolicy === 'noshownsfw')
                           $previewContent = 'nsfw';
                        else if($showPolicy === 'spoiler' || $showPolicy === 'noshowspoiler')
                           $previewContent = 'spoiler';
                        else
                           $previewContent = 'video||'.$httpPathPrefix.$uploadsArr[$k].'|'.$extension;
                     }
                     else
                        $previewContent = 'video||'.$httpPathPrefix.$uploadsArr[$k].'|'.$extension;
                     
                     $tplInput = array('fullSize' => $httpPathPrefix.$uploadsArr[$k],
                     'dimensions' => '',
                     'uploader' => $uploader,
                     'uploadDate' => date('d/m/Y à H:i:s', filemtime($filePath)),
                     'postID' => $attachments[$i]['id_post'],
                     'itemID' => $IDInGallery,
                     'slideshowPrevious' => $slideshowPreviousPost,
                     'slideshowNext' => $slideshowNextPost,
                     'content' => $previewContent);
                     
                     array_push($fullInput, $tplInput);
                  }
               }
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
                  $uploadsList .= ' ';
               $uploadsList .= $tplOutput[$i];
            }
         }
      }
   }
   
   if(strlen($uploadsList) == 0)
   {
      $finalTplInput['content'] = 'error';
   }
   else
   {
      $finalTplInput['content'] = 'listUploads||'.$uploadsList;
   }
   
   // Generates the whole page
   $display = TemplateEngine::parse('view/content/Uploads.composite.ctpl', $finalTplInput);
   WebpageHandler::wrap($display, 'Uploads du sujet "'.$topic->get('title').'"', $dialogs);
}
else
{
   $tpl = TemplateEngine::parse('view/content/Topic.fail.ctpl', array('error' => 'wrongURL'));
   WebpageHandler::wrap($tpl, 'Sujet introuvable');
}

?>

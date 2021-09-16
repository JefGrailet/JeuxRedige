<?php

/**
* This script allows a connected user to edit its own messages, or messages of other users if
* he or she uses a function account or has some particular authorizations.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in.
if(!LoggedUser::isLoggedIn())
{
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

require './model/Topic.class.php';
require './model/Post.class.php';
require './model/PostHistory.class.php';
require './model/Emoticon.class.php';
require './libraries/FormParsing.lib.php';
require './libraries/MessageParsing.lib.php';
require './view/intermediate/TopicHeader.ir.php';
require './view/intermediate/EditMessage.ir.php';
require './libraries/Buffer.lib.php'; // Needed for rendering previous uploads

if(!empty($_GET['id_post']) && preg_match('#^([0-9]+)$#', $_GET['id_post']))
{
   $getID = intval($_GET['id_post']);
   
   // Gets the post, its data and deals with errors if any.
   try
   {
      $post = new Post($getID);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingPost';
      $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   $attachArr = explode('|', $post->get('attachment'));
   if($attachArr === FALSE || (count($attachArr) == 1 && strlen($attachArr[0]) < 5))
      $attachArr = array();
   
   // Same applies with the related topic, with some other errors.
   try
   {
      $topic = new Topic($post->get('id_topic'));
      $topic->loadMetadata();
      $nbPostsBefore = $topic->countPosts($post->get('date'), true);
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';
      $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   // Boolean value telling if user is editing him- or herself or another user
   $editingSelf = true;
   if($post->get('posted_as') === 'anonymous' OR ($post->get('author') !== LoggedUser::$data['pseudo'] && $post->get('author') !== LoggedUser::$data['used_pseudo']))
      $editingSelf = false;
   
   // Last errors before we are sure the user can edit the message
   if(Utils::check($topic->get('is_locked')))
   {
      $tplInput = array('error' => 'lockedTopic');
      $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Ce sujet est verrouillé');
   }
   else if(!Utils::check(LoggedUser::$data['can_edit_all_posts']) && !$editingSelf)
   {
      $tplInput = array('error' => 'forbiddenEdition');
      $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisés à éditer ce message');
   }
   else if($post->get('posted_as') === 'author')
   {
      $tplInput = array('error' => 'automaticMessage');
      $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisés à éditer ce message');
   }
   else
   {
      // Webpage settings
      WebpageHandler::addCSS('topic');
      if(WebpageHandler::$miscParams['message_size'] === 'medium')
         WebpageHandler::addCSS('topic_medium');
      WebpageHandler::addCSS('topic_header');
      if($topic->hasGames())
         WebpageHandler::addCSS('media');
      WebpageHandler::addJS('topic_interaction');
      WebpageHandler::addJS('formatting');
      WebpageHandler::addJS('preview');
      WebpageHandler::changeContainer('topicContent');
      
      // Computes a link to the original message
      $postPage = ceil(($nbPostsBefore + 1) / WebpageHandler::$miscParams['posts_per_page']);
      $postURL = PathHandler::topicURL($topic->getAll(), $postPage).'#'.($nbPostsBefore + 1);
      
      // Dialogs for formatting
      $dialogs = '';
      $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
      if(!TemplateEngine::hasFailed($formattingDialogsTpl))
         $dialogs .= $formattingDialogsTpl;
      
      // Prepares the input for the whole page template
      $finalTplInput = array('header' => '',
      'originalMessage' => '',
      'previewPseudo' => $post->get('author'),
      'previewRank' => $post->get('posted_as'),
      'editionForm' => '',
      'uploadMenu' => '');
   
      // Topic header
      $headerTplInput = TopicHeaderIR::process($topic);
      $headerTpl = TemplateEngine::parse('view/content/TopicHeader.ctpl', $headerTplInput);
      if(TemplateEngine::hasFailed($headerTpl))
         WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du sujet');
      $finalTplInput['header'] = $headerTpl;
      
      // Takes care of previous uploads of this post (no matter if upload is still allowed or not)
      $existingUploads = '';
      $nbExistingUploads = 0;
      for($i = 0; $i < count($attachArr); $i++)
      {
         if(substr($attachArr[$i], 0, 7) === 'uploads')
         {
            $splitted = explode(':', $attachArr[$i]);
            $uploads = explode(',', $splitted[1]);
            $nbExistingUploads = count($uploads);
            
            if($nbExistingUploads == 0)
               break;
            
            $rendered = Buffer::render(Buffer::listMiniatures($uploads), 
                                      $post->get('id_topic'), 
                                      $post->get('id_post'));
            
            $existingUploads = 'yes||'.(Utils::UPLOAD_OPTIONS['bufferLimit'] - $nbExistingUploads).'|'.$rendered;
            
            break;
         }
      }
      
      // Makes adjustment for (NEW) uploads if it is relevant
      $nbNewUploads = 0;
      if(Utils::check(LoggedUser::$data['can_upload']) && Utils::check($topic->get('uploads_enabled')))
      {
         WebpageHandler::addJS('uploads');
         
         // File upload dialog
         $fileUploadDialogTpl = TemplateEngine::parse('view/dialog/UploadFile.dialog.ctpl');
         if(!TemplateEngine::hasFailed($fileUploadDialogTpl))
            $dialogs .= $fileUploadDialogTpl;

         // Upload menu
         $newUploadsList = Buffer::listContent();
         $nbNewUploads = count($newUploadsList[0]);
         
         $uploadTplInput = array('previousUploads' => $existingUploads,
                                 'uploadMessage' => 'newUpload',
                                 'uploadsView' => Buffer::render($newUploadsList));
         
         $uploadTpl = TemplateEngine::parse('view/content/EditMessage.upload.ctpl', $uploadTplInput);

         if(!TemplateEngine::hasFailed($uploadTpl))
            $finalTplInput['uploadMenu'] = $uploadTpl;
      }
      else
      {
         // Message stating uploads can no longer be done
         $uploadTplInput = array('previousUploads' => $existingUploads,
                                 'uploadMessage' => 'uploadDeactivated',
                                 'uploadsView' => '');
         
         // Modification if the difference is that the user cannot upload at allowed
         if(!Utils::check(LoggedUser::$data['can_upload']))
            $uploadTplInput['uploadMessage'] = 'uploadRefused';
         
         $uploadTpl = TemplateEngine::parse('view/content/EditMessage.upload.ctpl', $uploadTplInput);

         if(!TemplateEngine::hasFailed($uploadTpl))
            $finalTplInput['uploadMenu'] = $uploadTpl;
      }
      
      // Original message
      $originalMsgInput = array('title' => 'Aperçu du nouveau message',
      'avatar' => PathHandler::getAvatar($post->get('author')),
      'rank' => $post->get('posted_as'),
      'author' => $post->get('author'),
      'content' => MessageParsing::removeReferences(MessageParsing::parse($post->get('content'))));
      $originalMsgTpl = TemplateEngine::parse('view/content/PreviewPost.ctpl', $originalMsgInput);
      if(TemplateEngine::hasFailed($originalMsgTpl))
         WebpageHandler::wrap($originalMsgTpl, 'Une erreur est survenue lors de la lecture du message');
      $finalTplInput['originalMessage'] = $originalMsgTpl;
      
      // Prepares the input for the edition form
      $formTplInput = array('msgLink' => $postURL,
      'errors' => '',
      'messageID' => $getID,
      'content' => FormParsing::unparse($post->get('content')),
      'uploadOptions' => '',
      'reportPart' => EditMessageIR::process($post->getAll(), $editingSelf),
      'nbEdits' => $post->get('nb_edits'));
      
      try
      {
         $formTplInput['content'] = Emoticon::unparseEmoticonsShortcuts($formTplInput['content']);
      }
      catch(Exception $e) { }
      
      // Upload options input (if relevant)
      $uploadDisplayChoice = '';
      if(Utils::check($topic->get('uploads_enabled')))
      {
         if(!empty($_POST['upload_display_policy']) || $nbExistingUploads > 0)
         {
            if(!empty($_POST['upload_display_policy']))
               $uploadDisplayChoice = Utils::secure($_POST['upload_display_policy']);
            else
            {
               // Some parsing
               for($i = 0; $i < count($attachArr); $i++)
               {
                  if(substr($attachArr[$i], 0, 7) === 'uploads')
                  {
                     $exploded1 = explode(':', $attachArr[$i]);
                     $exploded2 = explode('_', $exploded1[0], 2);
                     
                     if(count($exploded2) == 1)
                        $uploadDisplayChoice = 'default';
                     else
                        $uploadDisplayChoice = $exploded2[1];
                     
                     break;
                  }
               }
            }
            
            $uploadOptionsStr = 'yes||';
            for($i = 0; $i < count(Utils::UPLOAD_OPTIONS['displayPolicies']); $i++)
            {
               if($i > 0)
                  $uploadOptionsStr .= '|';
            
               if($uploadDisplayChoice === Utils::UPLOAD_OPTIONS['displayPolicies'][$i])
                  $uploadOptionsStr .= ' selected="selected"';
               else
                  $uploadOptionsStr .= 'null';
            }
            
            $formTplInput['uploadOptions'] = $uploadOptionsStr;
         }
         else if(($nbNewUploads + $nbExistingUploads) > 0)
         {
            $formTplInput['uploadOptions'] = 'yes||null|null|null';
         }
      }
      
      if(!empty($_POST['sent']))
      {
         $content = Utils::secure($_POST['message']);
         $nbPreviousEdits = intval(Utils::secure($_POST['nbEdits']));
         $savingInHistory = true;
         if(!empty($_POST['noSaveInHistory']))
            $savingInHistory = false;

         if(strlen($content) > 0 && $nbPreviousEdits == $post->get('nb_edits'))
         {
            $formTplInput['content'] = $content;
         
            // Edits the message (possible exception if problem at the SQL server)
            try
            {
               $parsedContent = Emoticon::parseEmoticonsShortcuts($content);
               $parsedContent = FormParsing::parse($parsedContent);
               $copyOldPost = $post->getAll();
               $resEdition = $post->edit($parsedContent, $nbPreviousEdits);
               if($resEdition != 0 && $savingInHistory)
                  PostHistory::insert($copyOldPost);
               
               if(!$editingSelf && !empty($_POST['cancelReports']))
                  $post->cancelAlerts();
               
               // Takes care of upload if they are allowed (and if there remains space)
               if(Utils::check($topic->get('uploads_enabled')))
               {
                  // New uploads
                  $uploads = Buffer::listContent();
               
                  // Updates display policy (no matter if there are new uploads)
                  if($nbExistingUploads > 0 && 
                     strlen($uploadDisplayChoice) > 0 &&
                     in_array($uploadDisplayChoice, Utils::UPLOAD_OPTIONS['displayPolicies']))
                  {
                     for($i = 0; $i < count($attachArr); $i++)
                     {
                        if(substr($attachArr[$i], 0, 7) === 'uploads')
                        {
                           $newPrefix = 'uploads_'.$uploadDisplayChoice;
                           $exploded = explode(':', $attachArr[$i]);
                           $attachArr[$i] = $newPrefix.':'.$exploded[1];
                           
                           // Updates if they are no new uploads
                           if(count($uploads[0]) == 0)
                              $post->finalize(implode('|', $attachArr));
                           
                           break;
                        }
                     }
                  }
              
                  // Now takes care of new uploads
                  if(count($uploads[0]) > 0 && $nbExistingUploads < Utils::UPLOAD_OPTIONS['bufferLimit'])
                  {
                     $modifiedContent = FormParsing::relocate($post->get('content'), 
                                                              $topic->get('id_topic'), 
                                                              $post->get('id_post'));
                  
                     $useTruePseudo = false;
                     if($post->get('author') === LoggedUser::$data['pseudo'])
                        $useTruePseudo = true;
                     $maxUploads = Utils::UPLOAD_OPTIONS['bufferLimit'] - $nbExistingUploads;
                     $newUploadsString = Buffer::saveInTopic($uploads, 
                                                    $topic->get('id_topic'), 
                                                    $post->get('id_post'),
                                                    $useTruePseudo,
                                                    $maxUploads);
                     
                     if(strlen($newUploadsString) > 0 && $nbExistingUploads > 0)
                     {
                        for($i = 0; $i < count($attachArr); $i++)
                        {
                           if(substr($attachArr[$i], 0, 7) === 'uploads')
                           {
                              $attachArr[$i] .= ','.$newUploadsString;
                              break;
                           }
                        }
                        
                        $post->finalize(implode('|', $attachArr), $modifiedContent);
                     }
                     else if(strlen($newUploadsString) > 0)
                     {
                        $uploadPrefix = 'uploads';
                        
                        // Display policy chosen by the user
                        if(strlen($uploadDisplayChoice) > 0)
                        {
                           if(in_array($uploadDisplayChoice, Utils::UPLOAD_OPTIONS['displayPolicies']))
                              $uploadPrefix .= '_'.$uploadDisplayChoice;
                        }
                     
                        array_push($attachArr, $uploadPrefix.':'.$newUploadsString);
                        
                        $post->finalize(implode('|', $attachArr), $modifiedContent);
                     }
                  }
               }
               
               header('Location:'.$postURL);
               $successTplInput = array('target' => $postURL);
               $successTpl = TemplateEngine::parse('view/content/EditMessage.success.ctpl', $successTplInput);
               WebpageHandler::resetDisplay();
               WebpageHandler::wrap($successTpl, 'Message édité avec succès');
            }
            catch(Exception $e)
            {
               $formTplInput['errors'] = 'dbError';
               
               /*
               * To consider in the future: edit of the text can have been completed because the 
               * exception was thrown by another subsequent method (cancelAlerts(), finalize() 
               * and insert() from PostHistory), but there is little chance this happens. 
               * Furthermore, the user will see that his/her post has been updated thanks to the 
               * preview.
               */
            }
         }
         else
         {
            if($post->get('nb_edits') > $nbPreviousEdits)
            {
               $formTplInput['errors'] = 'concurrentEdit';
               $formTplInput['content'] = $content;
            }
            else
               $formTplInput['errors'] = 'emptyField';
         }
      }
      
      // Produces the edition form and the complete page
      $formTpl = TemplateEngine::parse('view/content/EditMessage.form.ctpl', $formTplInput);
      if(TemplateEngine::hasFailed($formTpl))
         WebpageHandler::wrap($formTpl, 'Une erreur est survenue lors de la création du formulaire');
      $finalTplInput['editionForm'] = $formTpl;
      $finalTpl = TemplateEngine::parse('view/content/EditMessage.composite.ctpl', $finalTplInput);
      WebpageHandler::wrap($finalTpl, 'Editer un message', $dialogs);
   }
}
else
{
   $tplInput = array('error' => 'missingPost');
   $tpl = TemplateEngine::parse('view/content/EditMessage.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le message est manquant');
}

?>

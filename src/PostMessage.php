<?php

/**
* Script to post a new message on any topic. It is used for posting both as an anonymous and
* as a logged user. This is also the main reason the script is so "long".
*/

require './libraries/Header.lib.php';

require './model/Topic.class.php';
require './model/Post.class.php';
require './model/Emoticon.class.php';
require './libraries/FormParsing.lib.php';
require './view/intermediate/TopicHeader.ir.php';
require './libraries/Anonymous.lib.php';

WebpageHandler::redirectionAtLoggingIn();

$dialogs = '';

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);
   
   // Gets the topic and the related data; prints error whenever one occurs
   try
   {
      $topic = new Topic($getID);
      $topic->loadMetadata();
      $nbPosts = $topic->countPosts();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingTopic';
      
      $tpl = TemplateEngine::parse('view/content/PostMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet introuvable');
   }
   
   // Error if the topic is locked
   if(Utils::check($topic->get('is_locked')))
   {
      $tplInput = array('error' => 'lockedTopic');
      $tpl = TemplateEngine::parse('view/content/PostMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet verrouillé');
   }
   // Error if anons cannot post
   else if(!LoggedUser::isLoggedIn() && !Utils::check($topic->get('is_anon_posting_enabled')))
   {
      $tplInput = array('error' => 'anonForbidden');
      $tpl = TemplateEngine::parse('view/content/PostMessage.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Vous ne pouvez pas répondre à ce sujet');
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
      WebpageHandler::changeContainer('topicContent');
      
      // Prepares the input for the whole page template
      $finalTplInput = array('header' => '',
      'previewPseudo' => '',
      'previewRank' => '',
      'replyForm' => '',
      'uploadMenu' => '');
   
      $dialogs = '';

      // Formatting, preview features
      if(LoggedUser::isLoggedIn())
      {
         // Dialogs for formatting
         $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
         if(!TemplateEngine::hasFailed($formattingDialogsTpl))
            $dialogs .= $formattingDialogsTpl;
         
         WebpageHandler::addJS('formatting');
         WebpageHandler::addJS('preview');
         $finalTplInput['previewPseudo'] = LoggedUser::$data['used_pseudo'];
         $finalTplInput['previewRank'] = LoggedUser::rank();
      }
   
      // Makes adjustment for uploads if it is relevant
      if(LoggedUser::isLoggedIn() && Utils::check($topic->get('uploads_enabled')))
      {
         WebpageHandler::addJS('uploads');
         require './libraries/Buffer.lib.php';
         
         // File upload dialog
         $fileUploadDialogTpl = TemplateEngine::parse('view/dialog/UploadFile.dialog.ctpl');
         if(!TemplateEngine::hasFailed($fileUploadDialogTpl))
            $dialogs .= $fileUploadDialogTpl;
      }
      
      // Generates upload window view
      $nbUploads = 0; // Useful later
      if(LoggedUser::isLoggedIn())
      {
         if(Utils::check($topic->get('uploads_enabled')) && Utils::check(LoggedUser::$data['can_upload']))
         {
            $uploadsList = Buffer::listContent();
            $nbUploads = count($uploadsList[0]);
         
            $uploadTplInput = array('uploadMessage' => 'newUpload',
                                    'uploadsView' => Buffer::render($uploadsList));
            
            $uploadTpl = TemplateEngine::parse('view/content/PostMessage.upload.ctpl', $uploadTplInput);

            if(!TemplateEngine::hasFailed($uploadTpl))
               $finalTplInput['uploadMenu'] = $uploadTpl;
         }
         else
         {
            $uploadTplInput = array('uploadMessage' => 'uploadDeactivated',
                                    'uploadsView' => '');
            
            // Uploads refused
            if(!Utils::check(LoggedUser::$data['can_upload']))
               $uploadTplInput['uploadMessage'] = 'uploadRefused';
            
            $uploadTpl = TemplateEngine::parse('view/content/PostMessage.upload.ctpl', $uploadTplInput);

            if(!TemplateEngine::hasFailed($uploadTpl))
               $finalTplInput['uploadMenu'] = $uploadTpl;
         }
      }
      
      // Prepares the input for the reply form
      $formTplInput = array('errors' => '', 
      'topicID' => $getID, 
      'anonPseudoStatus' => 'new||null', 
      'showFormattingUI' => 'no', 
      'content' => '', 
      'uploadOptions' => '', 
      'formEnd' => 'anon');
      
      if(LoggedUser::isLoggedIn())
         $formTplInput['showFormattingUI'] = 'yes';
      
      // Upload options input (if necessary; occurs when message is not submitted)
      if(LoggedUser::isLoggedIn() && Utils::check($topic->get('uploads_enabled')))
      {
         if(!empty($_POST['upload_display_policy']))
         {
            $uploadDisplayChoice = Utils::secure($_POST['upload_display_policy']);
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
         else if($nbUploads > 0)
         {
            $formTplInput['uploadOptions'] = 'yes||null|null|null';
         }
      }
      
      $anonPseudo = '';
      if(LoggedUser::isLoggedIn())
      {
         $formTplInput['anonPseudoStatus'] = '';
         $formTplInput['formEnd'] = 'askPreview';
      }
      else
      {
         $anonPseudo = Anonymous::getPseudo();
         if(strlen($anonPseudo) > 3 && strlen($anonPseudo) < 21)
         {
            $formTplInput['anonPseudoStatus'] = 'existing||';
            $formTplInput['anonPseudoStatus'] .= $anonPseudo;
         }
      }
   
      // Topic header
      $headerTplInput = TopicHeaderIR::process($topic);
      $headerTpl = TemplateEngine::parse('view/content/TopicHeader.ctpl', $headerTplInput);
      if(TemplateEngine::hasFailed($headerTpl))
         WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du sujet');
      $finalTplInput['header'] = $headerTpl;
      
      // Last thing to prepare: for registered user, abitility to quote another post
      if(LoggedUser::isLoggedIn() && !empty($_GET['quoting']) && preg_match('#^([0-9]+)$#', $_GET['quoting']))
      {
         require './view/intermediate/QuoteMessage.ir.php';
         
         $postToQuote = intval(Utils::secure($_GET['quoting']));
         
         try
         {
            $quotedPost = new Post($postToQuote);
            $postArr = $quotedPost->getAll();
            $postArr['content'] = FormParsing::unparse($postArr['content']);
            $readyForQuote = QuoteMessageIR::process($postArr);
            
            $formTplInput['content'] = $readyForQuote.$formTplInput['content'];
         }
         // Nothing should happen if the post does not exist or if DB fails for some reason
         catch(Exception $e) { }
      }
   
      if(!empty($_POST['sent']))
      {
         $content = Utils::secure($_POST['message']);
         $formTplInput['content'] = $content; // If we have to display the form again
         
         /*
         * Advanced mode : while using the quick reply form, a logged user wants to write his/her
         * message with format code/preview; he/she clicks on a special button. Upon this, the
         * content he/she already wrote is copied into the "extended" form.
         */
         
         if($_POST['sent'] == 'Mode avancé')
         {
            $formTpl = TemplateEngine::parse('view/content/PostMessage.form.ctpl', $formTplInput);
            if(TemplateEngine::hasFailed($formTpl))
               WebpageHandler::wrap($headerTpl, 'Une erreur est survenue');
            
            $finalTplInput['replyForm'] = $formTpl;
            $finalTpl = TemplateEngine::parse('view/content/PostMessage.composite.ctpl', $finalTplInput);
            WebpageHandler::wrap($finalTpl, 'Poster un message', $dialogs);
         }
         
         // Is there any content ? Error if no content at all.
         if(strlen($content) == 0)
            $formTplInput['errors'] .= 'emptyField|';
         
         /*
         * This part deals with anonymous users. First part checks/generates the pseudo, the 
         * second checks the catpcha and the last activity date (prevents anon spam).
         */
         
         if(!LoggedUser::isLoggedIn())
         {
            if(strlen($anonPseudo) == 0)
            {
               $inputAnonPseudo = Utils::secure($_POST['pseudo']);
               
               // Randomly generated pseudo
               if(strlen($inputAnonPseudo) == 0)
               {
                  $randAnonPseudo = '';
                  do
                  {
                     $randAnonPseudo = 'Anonyme_'.str_pad(mt_rand(0, pow(10, 4) - 1), 4, '0', STR_PAD_LEFT);
                  }
                  while(!Anonymous::isAvailable($randAnonPseudo));
                  $anonPseudo = $randAnonPseudo;
               }
               else if(strlen($inputAnonPseudo) > 20)
                  $formTplInput['errors'] .= 'anonPseudoTooLong|';
               else if(!Anonymous::isAvailable($inputAnonPseudo))
                  $formTplInput['errors'] .= 'anonPseudoUnavailable|';
               else
               {
                  $formTplInput['anonPseudoStatus'] = 'new||'.$inputAnonPseudo;
                  $anonPseudo = $inputAnonPseudo;
               }
            }
            
            // Checks captcha and last activity
            $lastActivity = Anonymous::lastActivity($anonPseudo);
            if(sha1(Utils::secure($_POST['captcha'])) != $_SESSION['captcha'])
               $formTplInput['errors'] .= 'wrongCaptcha|';
            else if($lastActivity < WebpageHandler::$miscParams['consecutive_anon_posts_delay'] && $lastActivity >= 0)
               $formTplInput['errors'] .= 'tooManyPostsAnon|';
         }
         // For regular users, we check that they do not create too many messages (max. one every 30s)
         else
         {
            $delay = 60;
            try
            {
               $delay = Post::getUserDelay();
            }
            catch(Exception $e)
            {
               // Nothing, so far
            }
            
            if($delay < WebpageHandler::$miscParams['consecutive_posts_delay'])
               $formTplInput['errors'] .= 'tooManyPostsUser|';
         }
         
         // No error at all; insertion and topic update are done in one single transaction
         if(strlen($formTplInput['errors']) == 0)
         {
            Database::beginTransaction();
            try
            {
               if(!LoggedUser::isLoggedIn())
               {
                  $parsedContent = nl2br($content); // No format code for anonymous users
                  $newPost = Post::insert($topic->get('id_topic'), $parsedContent, $anonPseudo);
               }
               else
               {
                  $parsedContent = Emoticon::parseEmoticonsShortcuts($content);
                  $parsedContent = FormParsing::parse($parsedContent);
                  $newPost = Post::insert($topic->get('id_topic'), $parsedContent);
               }
               $topic->update($newPost->getAll());
               $newNbPosts = $topic->countPosts();
               
               Database::commit();
            }
            // Rollback and error display in case of error with SQL
            catch(Exception $e)
            {
               Database::rollback();
               
               $formTplInput['errors'] = 'dbError';
               $formTpl = TemplateEngine::parse('view/content/PostMessage.form.ctpl', $formTplInput);
               if(TemplateEngine::hasFailed($formTpl))
                  WebpageHandler::wrap($headerTpl, 'Une erreur est survenue');
               
               $finalTplInput['replyForm'] = $formTpl;
               $finalTpl = TemplateEngine::parse('view/content/PostMessage.composite.ctpl', $finalTplInput);
               WebpageHandler::wrap($finalTpl, 'Poster un message', $dialogs);
            }
            
            // Computes an URL to the new message
            $lastPage = ceil($newNbPosts / WebpageHandler::$miscParams['posts_per_page']);
            $urlNewPost = PathHandler::topicURL($topic->getAll(), $lastPage).'#'.($nbPosts + 1);
            
            // Takes care of upload if allowed (and if using full form)
            $uploadsOK = LoggedUser::isLoggedIn() && Utils::check($topic->get('uploads_enabled'));
            if($uploadsOK && (!empty($_POST['origin']) && $_POST['origin'] === 'fullForm'))
            {
               $uploads = Buffer::listContent();
               if(count($uploads[0]) > 0)
               {
                  $uploadsString = Buffer::saveInTopic($uploads, 
                                              $topic->get('id_topic'), 
                                              $newPost->get('id_post'));
                  
                  if(strlen($uploadsString) > 0)
                  {
                     try
                     {
                        $uploadPrefix = 'uploads';
                        
                        // Display policy chosen by the user
                        if(!empty($_POST['upload_display_policy']))
                        {
                           $displayPolicy = Utils::secure($_POST['upload_display_policy']);
                           if(in_array($displayPolicy, Utils::UPLOAD_OPTIONS['displayPolicies']))
                              $uploadPrefix .= '_'.$displayPolicy;
                        }
                        
                        $modifiedContent = FormParsing::relocate($newPost->get('content'), 
                                                                 $topic->get('id_topic'), 
                                                                 $newPost->get('id_post'));
                        
                        $newPost->finalize($uploadPrefix.':'.$uploadsString, $modifiedContent);
                     }
                     catch(Exception $e) {}
                  }
               }
            }
            
            /*
            * Redirection to the (new) last page of the topic; success message is displayed if 
            * the redirection fails.
            */
            
            header('Location:'.$urlNewPost);
            $tplInput = array('target' => $urlNewPost);
            $successTpl = TemplateEngine::parse('view/content/PostMessage.success.ctpl', $tplInput);
            WebpageHandler::resetDisplay();
            WebpageHandler::wrap($successTpl, 'Message posté avec succès');
         }
         // Displays errors
         else
         {
            $formTplInput['errors'] = substr($formTplInput['errors'], 0, -1); // Removes last "|"
            $formTpl = TemplateEngine::parse('view/content/PostMessage.form.ctpl', $formTplInput);
            if(TemplateEngine::hasFailed($formTpl))
               WebpageHandler::wrap($headerTpl, 'Une erreur est survenue');
            
            $finalTplInput['replyForm'] = $formTpl;
            $finalTpl = TemplateEngine::parse('view/content/PostMessage.composite.ctpl', $finalTplInput);
            WebpageHandler::wrap($finalTpl, 'Poster un message', $dialogs);
         }
      }
      // Default form
      else
      {
         $formTpl = TemplateEngine::parse('view/content/PostMessage.form.ctpl', $formTplInput);
         if(TemplateEngine::hasFailed($formTpl))
            WebpageHandler::wrap($headerTpl, 'Une erreur est survenue');
         
         $finalTplInput['replyForm'] = $formTpl;
         $finalTpl = TemplateEngine::parse('view/content/PostMessage.composite.ctpl', $finalTplInput);
         WebpageHandler::wrap($finalTpl, 'Poster un message', $dialogs);
      }
   }
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/PostMessage.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Sujet introuvable');
}
   
?>

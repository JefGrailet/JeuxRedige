<?php

/**
* This script allows a logged user to edit a topic regarding its main options, title, thumbnail 
* and keywords. Only the author of the topic or an authorized user may be able to edit it. The 
* interface is also richer than the interface for topic creation, since the topic header is also
* displayed with the buttons to lock/unlock/delete the topic if necessary.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::addJS('uploads'); // Activated by default, for now
WebpageHandler::addJS('keywords');

// This script is only accessible to a logged in user...
if(!LoggedUser::isLoggedIn())
{
   $tpl = TemplateEngine::parse('view/content/EditTopic.fail.ctpl', array('error' => 'notConnected'));
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
// ... which is allowed to create/edit topics.
else if(!Utils::check(LoggedUser::$data['can_create_topics']))
{
   $tpl = TemplateEngine::parse('view/content/EditTopic.fail.ctpl', array('error' => 'notAllowed'));
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas (encore) autorisé à créer/modifier des sujets');
}

require './model/Topic.class.php';
require './model/Tag.class.php';
require './libraries/Keywords.lib.php';
require './libraries/Buffer.lib.php';
require './view/intermediate/TopicHeader.ir.php';

// Inits the string for dialog boxes
$dialogs = '';

if(!empty($_GET['id_topic']) && preg_match('#^([0-9]+)$#', $_GET['id_topic']))
{
   $getID = intval($_GET['id_topic']);
   
   // Gets the topic (+ errors if DB disturbance or missing topic)
   try
   {
      $topic = new Topic($getID);
      $topic->loadMetadata();
      $nbPosts = $topic->countPosts();
      $keywords = $topic->getKeywordsSimple();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingTopic';
      $tpl = TemplateEngine::parse('view/content/EditTopic.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Sujet introuvable');
   }
   
   // Dialog box for custom thumbnail creation
   $dialogTpl = TemplateEngine::parse('view/dialog/CustomThumbnail.dialog.ctpl');
   if(!TemplateEngine::hasFailed($dialogTpl))
      $dialogs = $dialogTpl;
   
   // Depending on the user, lock and delete dialog boxes are also made available
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
      $tplInput = array('topicID' => $getID);
      $dialogTpl = TemplateEngine::parse('view/dialog/DeleteTopic.dialog.ctpl', $tplInput);
      if(!TemplateEngine::hasFailed($dialogTpl))
         $dialogs .= $dialogTpl;
   }
   
   // Additional display settings
   WebpageHandler::addCSS('topic');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('topic_medium');
   WebpageHandler::addCSS('topic_header');
   WebpageHandler::addJS('topic_interaction');
   WebpageHandler::addJS('uploads'); // Custom thumbnail creation enabled
   WebpageHandler::addJS('keywords');
   WebpageHandler::changeContainer('topicContent');
   
   // Array with the input for the form, in a similar fashion to that of NewTopic.php (2 usages)
   $formData = array('header' => '',
   'topicID' => $getID,
   'errors' => '',
   'title' => $topic->get('title'),
   'thumbnailPath' => '',
   'anonChecked' => '',
   'uploadsChecked' => '',
   'keywordsList' => '',
   'keywords' => '',
   'thumbnail' => $topic->get('thumbnail'));
   
   // Thumbnail (relative path)
   if($formData['thumbnail'] === 'none')
      $formData['thumbnailPath'] = PathHandler::HTTP_PATH().'defaultthumbnail.jpg';
   else if($formData['thumbnail'] === 'CUSTOM')
      $formData['thumbnailPath'] = PathHandler::HTTP_PATH().'upload/topics/'.$getID.'/thumbnail.jpg';
   else
      $formData['thumbnailPath'] = PathHandler::HTTP_PATH().'upload/tmp/'.LoggedUser::$data['pseudo'].'/'.$formData['thumbnail'];
   
   // Checkbox(es)
   if(Utils::check($topic->get('is_anon_posting_enabled')))
      $formData['anonChecked'] = 'checked';
   
   if(Utils::check($topic->get('uploads_enabled')))
      $formData['uploadsChecked'] = 'checked';
   
   // Puts the keywords back into a single string
   $parsedKeywords = implode('|', $keywords);
   $formData['keywords'] = $parsedKeywords;
   $formData['keywordsList'] = Keywords::display($keywords);
   
   // Topic header
   $headerTplInput = TopicHeaderIR::process($topic, 'edition');
   $headerTpl = TemplateEngine::parse('view/content/TopicHeader.ctpl', $headerTplInput);
   if(TemplateEngine::hasFailed($headerTpl))
      WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du sujet');
   $formData['header'] = $headerTpl;
   
   // Errors before reaching the form : locked topic and forbidden edition
   if(Utils::check($topic->get('is_locked')))
   {
      $tplInput = array('error' => 'lockedTopic');
      $tpl = TemplateEngine::parse('view/content/EditTopic.fail.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($tpl, 'Ce sujet est verrouillé');
   }
   else if(!Utils::check(LoggedUser::$data['can_edit_all_posts']) && ($topic->get('author') != LoggedUser::$data['pseudo'] && $topic->get('author') != LoggedUser::$data['used_pseudo']))
   {
      $tplInput = array('error' => 'forbiddenEdition');
      $tpl = TemplateEngine::parse('view/content/EditTopic.fail.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisés à éditer ce sujet');
   }
   else
   {
      if(!empty($_POST['sent']))
      {
         $formData['title'] = Utils::secure($_POST['title']);
         $formData['thumbnail'] = Utils::secure($_POST['thumbnail']);
         $formData['keywords'] = Utils::secure($_POST['keywords']);
         
         $newKeywords = explode('|', $formData['keywords']);
         $formData['keywordsList'] = Keywords::display($newKeywords);
         
         // Checkboxes for overall configuration
         $anonPosting = false;
         if(isset($_POST['anon_posting']))
         {
            $anonPosting = true;
            $formData['anonChecked'] = 'checked';
         }
         else
            $formData['anonChecked'] = '';
         
         $enableUploads = false;
         if(isset($_POST['enable_uploads']))
         {
            $enableUploads = true;
            $formData['uploadsChecked'] = 'checked';
         }
         else
            $formData['uploadsChecked'] = '';

         $lengthTitle = strlen($formData['title']);
         $noKeywords = count($newKeywords) == 1 && strlen($newKeywords[0]) == 0;
         // Empty title or too long title errors
         if($lengthTitle == 0 || $lengthTitle > 125 || $noKeywords)
         {
            $formData['errors'] = '';
            if($lengthTitle == 0)
               $formData['errors'] .= 'emptyField|';
            else if($lengthTitle > 125)
               $formData['errors'] .= 'titleTooLong|';
            if($noKeywords)
               $formData['errors'] .= 'noKeywords|';
            
            $formData['errors'] = substr($formData['errors'], 0, -1);
            $formTpl = TemplateEngine::parse('view/content/EditTopic.form.ctpl', $formData);
            WebpageHandler::wrap($formTpl, 'Editer un sujet', $dialogs);
         }
         else
         {
            // Thumbnail edition (permanently saves the picture if new)
            $thumbnail = $topic->get('thumbnail');
            if($formData['thumbnail'] !== 'none' && $formData['thumbnail'] !== 'CUSTOM' && file_exists(PathHandler::WWW_PATH().substr($formData['thumbnail'], 2)))
            {
               $thumbnail = 'CUSTOM';
               $fileName = substr(strrchr($formData['thumbnail'], '/'), 1);
               Buffer::save('upload/topics/'.$topic->get('id_topic'), $fileName, 'thumbnail');
            }
         
            // Actual edition of the topic
            try
            {
               $topic->edit($formData['title'], $thumbnail, $anonPosting, $enableUploads);
            }
            catch(Exception $e)
            {
               $formData['errors'] = 'dbError';
               $formTpl = TemplateEngine::parse('view/content/EditTopic.form.ctpl', $formData);
               WebpageHandler::wrap($formTpl, 'Editer un sujet', $dialogs);
            }
            
            /* Keywords editions : original keywords missing in the new keyword array are deleted 
            * keywords appearing for the first time in the new array are added. */

            $nbCommonKeywords = sizeof(Keywords::common($keywords, $newKeywords));
            $keywordsToDelete = Keywords::distinct($keywords, $newKeywords);
            $keywordsToAdd = Keywords::distinct($newKeywords, $keywords);
            
            // Deletes the keywords absent from the new string
            try
            {
               Tag::unmapTopic($topic->get('id_topic'), $keywordsToDelete);
            }
            catch(Exception $e) { } // No dedicated error printed for now
            
            // Adds the new keywords (maximum 10 - $nbCommonKeywords)
            for($j = 0; $j < count($keywordsToAdd) && $j < (10 - $nbCommonKeywords); $j++)
            {
               try
               {
                  $tag = new Tag($keywordsToAdd[$j]);
                  $tag->mapToTopic($topic->get('id_topic'));
               }
               catch(Exception $e)
               {
                  continue;
               }
            }
            
            // Cleans the DB from tags that are no longer mapped to any topic
            Tag::cleanOrphanTags();
            
            // Redirection to the topic
            $finalURL = PathHandler::topicURL($topic->getAll());
            header('Location:'.$finalURL);
            
            // Success page
            $tplInput = array('target' => $finalURL);
            $successPage = TemplateEngine::parse('view/content/EditTopic.success.ctpl', $tplInput);
            WebpageHandler::resetDisplay();
            WebpageHandler::wrap($successPage, 'Sujet édité avec succès');
         }
      }
      else
      {
         $formTpl = TemplateEngine::parse('view/content/EditTopic.form.ctpl', $formData);
         WebpageHandler::wrap($formTpl, 'Editer un sujet', $dialogs);
      }
   }
}
else
{
   $tpl = TemplateEngine::parse('view/content/EditTopic.fail.ctpl', array('error' => 'missingTopic'));
   WebpageHandler::wrap($tpl, 'Le sujet à éditer est manquant');
}

?>

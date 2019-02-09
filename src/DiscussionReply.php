<?php

/**
* This script allows user to add a reply to a "ping pong"/private discussion.
*/

require './libraries/Header.lib.php';

require './model/Ping.class.php';
require './model/PingPong.class.php';
require './model/Emoticon.class.php';
require './libraries/FormParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::noRedirectionAtLoggingOut();

// User must be (of course) logged in to see this page.
if(!LoggedUser::isLoggedIn())
{
   WebpageHandler::wrap(TemplateEngine::parse('view/user/NotLoggedIn.ctpl'), 'Erreur');
}

if(!empty($_GET['id_ping']) && preg_match('#^([0-9]+)$#', $_GET['id_ping']))
{
   $getID = intval($_GET['id_ping']);
   
   // Obtains the discussion and its posts
   $discussion = null;
   try
   {
      $discussion = new PingPong($getID);
   }
   // Handles exceptions
   catch(Exception $e)
   {
      /*
       * We can re-use PrivateDiscussion.fail.ctpl because the possible errors remain the same in 
       * the context of this script.
       */
      
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingPing';
      $tpl = TemplateEngine::parse('view/user/PrivateDicussion.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Discussion introuvable');
   }
   
   if($discussion->get('state') === 'archived')
   {
      $otherUser = $discussion->get('emitter');
      if(LoggedUser::$data['pseudo'] === $otherUser)
         $otherUser = $discussion->get('receiver');
      
      $backToDiscussion = 'yes||./PrivateDiscussion.php?id_ping='.$discussion->get('id_ping');
      
      $noFormTplInput = array('otherParty' => $otherUser, 'showLink' => $backToDiscussion);
      $noFormTpl = TemplateEngine::parse('view/user/DiscussionReply.fail.ctpl', $noFormTplInput);
      WebpageHandler::wrap($noFormTpl, 'Cette discussion a été archivée');
   }
   
   // Prepares the input for the discussion template and reply form
   $finalTplInput = array('newPingForm' => '',
   'previewPseudo' => LoggedUser::$data['pseudo']);
   
   $formTplInput = array('otherParty' => $discussion->get('emitter'),
   'showTitle' => 'yes||'.$discussion->get('title'),
   'errors' => '', 
   'discussionID' => $discussion->get('id_ping'), 
   'content' => '', 
   'toArchive' => '', 
   'formEnd' => 'askPreview');
   
   if(LoggedUser::$data['pseudo'] === $discussion->get('emitter'))
      $formTplInput['otherParty'] = $discussion->get('receiver');
   
   // DisplaySettings
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('ping_medium');
   else
      WebpageHandler::addCSS('ping');
   WebpageHandler::addJS('formatting');
   WebpageHandler::addJS('ping_interaction');
   WebpageHandler::addJS('preview');
   WebpageHandler::changeContainer('pingsContent');
   
   // Dialogs for formatting
   $dialogs = '';
   $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
   if(!TemplateEngine::hasFailed($formattingDialogsTpl))
      $dialogs = $formattingDialogsTpl;
   
   // Form has been activated
   if(!empty($_POST['sent']))
   {
      $content = Utils::secure($_POST['message']);
      $formTplInput['content'] = $content;
      if(isset($_POST['archive']) && $_POST['archive'] === 'Yes')
         $formTplInput['toArchive'] = 'yes';
      
      // User just asked to move to advanced mode (with preview); re-displays form and quits
      if($_POST['sent'] == 'Mode avancé')
      {
         $formTpl = TemplateEngine::parse('view/user/DiscussionReply.form.ctpl', $formTplInput);
         if(!TemplateEngine::hasFailed($formTpl))
            $finalTplInput['newPingForm'] = $formTpl;
         else
            WebpageHandler::wrap($formTpl, 'Une erreur est survenue lors de l\'accès à la discussion');
         
         $display = TemplateEngine::parse('view/user/PingFormPreview.ctpl', $finalTplInput);
         WebpageHandler::wrap($display, 'Répondre à la discussion '.$discussion->get('title').'', $dialogs);
      }
      
      // Gets the delay between current time and the lattest message sent by this user
      $delay = 3600;
      try
      {
         $delay = PingPong::getUserDelayBis();
      }
      catch(Exception $e) { }
      
      // Possible "light" errors
      if(strlen($content) == 0)
         $formTplInput['errors'] .= 'emptyMessage|';
      if($delay < WebpageHandler::$miscParams['consecutive_posts_delay'])
         $formTplInput['errors'] .= 'tooManyPings|';
      
      // Everything is OK, new message will be added (except if there is a DB error)
      if($formTplInput['errors'] === '')
      {
         $success = true;
         $newNbPongs = 0;
         Database::beginTransaction(); // SQL requests should be performed as one transaction
         try
         {
            $parsedContent = Emoticon::parseEmoticonsShortcuts($content);
            $parsedContent = FormParsing::parse($parsedContent);
         
            $discussion->append($parsedContent);
            $newNbPongs = $discussion->countPongs();
            
            if(!empty($_POST['archive']) && $_POST['archive'] === 'Yes')
               $discussion->archive();
            
            Database::commit();
         }
         catch(Exception $e)
         {
            Database::rollback();
            
            $success = false;
            $formTplInput['errors'] = 'dbError';
         }
         
         if($success)
         {
            // Redirection to the (possible new) last page of the discussion
            $lastPage = ceil($newNbPongs / WebpageHandler::$miscParams['posts_per_page']);
            $newDiscussionURL = './PrivateDiscussion.php?id_ping='.$discussion->get('id_ping').'&page='.$lastPage.'#'.$newNbPongs;
            header('Location:'.$newDiscussionURL);
            
            // Success page
            $tplInput = array('target' => $newDiscussionURL);
            $successPage = TemplateEngine::parse('view/user/DiscussionReply.success.ctpl', $tplInput);
            WebpageHandler::resetDisplay();
            WebpageHandler::wrap($successPage, 'Répondre à la discussion '.$discussion->get('title'));
         }
      }
      // Some error occurred: form will be displayed again with errors
      else
      {
         $formTplInput['errors'] = substr($formTplInput['errors'], 0, -1); // Removes last "|"
      }
   }

   // Displays form
   $formTpl = TemplateEngine::parse('view/user/DiscussionReply.form.ctpl', $formTplInput);
   if(!TemplateEngine::hasFailed($formTpl))
      $finalTplInput['newPingForm'] = $formTpl;
   else
      WebpageHandler::wrap($formTpl, 'Une erreur est survenue lors de l\'accès à la discussion');
   
   $display = TemplateEngine::parse('view/user/PingFormPreview.ctpl', $finalTplInput);
   WebpageHandler::wrap($display, 'Répondre à la discussion '.$discussion->get('title'), $dialogs);
}
else
{
   $tpl = TemplateEngine::parse('view/user/PrivateDiscussion.fail.ctpl', array('error' => 'wrongURL'));
   WebpageHandler::wrap($tpl, 'Discussion introuvable');
}

?>

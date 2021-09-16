<?php

/**
* This script displays a private discussion.
*/

require './libraries/Header.lib.php';

require './model/Ping.class.php';
require './model/PingPong.class.php';
require './model/User.class.php';
require './libraries/MessageParsing.lib.php';
require './view/intermediate/Pong.ir.php';

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
   
   // Prepares the input for the discussion template
   $finalTplInput = array('title' => '', 
   'pageConfig' => '', 
   'advancedPongForm' => './DiscussionReply.php?id_ping='.$getID, 
   'pongs' => '', 
   'newPongForm' => '');

   // Obtains the discussion and its posts
   $pongs = null;
   $discussion = null;
   try
   {
      $discussion = new PingPong($getID);
      $nbPongs = $discussion->countPongs();

      // Gets current page and computes the first index to retrieve the messages
      $currentPage = 1;
      $nbPages = ceil($nbPongs / WebpageHandler::$miscParams['posts_per_page']);
      $firstPost = 0;
      if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
      {
         $getPage = intval($_GET['page']);
         if($getPage <= $nbPages)
         {
            $currentPage = $getPage;
            $firstPost = ($getPage - 1) * WebpageHandler::$miscParams['posts_per_page'];
         }
      }
      
      $finalTplInput['pagesConfig'] = WebpageHandler::$miscParams['posts_per_page'].'|'.$nbPongs.'|'.$currentPage;
      $finalTplInput['pagesConfig'] .= '|./PrivateDiscussion.php?id_ping='.$discussion->get('id_ping').'&page=[]';
      $finalTplInput['pagesConfig'] .= '|GetPongs.php?id_ping='.$discussion->get('id_ping');
      $finalTplInput['pagesConfig'] .= '|RefreshPing.php?id_ping='.$discussion->get('id_ping');
      $finalTplInput['pagesConfig'] .= '|CheckPing.php?id_ping='.$discussion->get('id_ping'); // To notify the discussion is fully read after auto refresh
      $pongs = $discussion->getPongs($firstPost, WebpageHandler::$miscParams['posts_per_page']);
      $discussion->updateView();
   }
   // Handles exceptions
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingPing';
      $tpl = TemplateEngine::parse('view/user/PrivateDiscussion.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Discussion introuvable');
   }
   
   $finalTplInput['title'] = $discussion->get('title');
   
   // Checks online status of users
   $online = null;
   try
   {
      $online = User::checkOnlineStatus(array($discussion->get('emitter'), $discussion->get('receiver')));
   }
   catch(Exception $e) {}
   
   // Formats and renders the pongs
   $fullInput = array();
   for($i = 0; $i < count($pongs); $i++)
   {
      if($online != null && in_array($pongs[$i]['author'], $online))
         $pongs[$i]['online'] = true;
      $pongs[$i]['message'] = MessageParsing::parse($pongs[$i]['message'], ($firstPost + $i + 1));
   
      $pongIR = PongIR::process($pongs[$i], ($firstPost + $i + 1));
      array_push($fullInput, $pongIR);
   }
   $fullInput = Utils::removeSeconds($fullInput);
   
   $pongsTpl = TemplateEngine::parseMultiple('view/user/Pong.ctpl', $fullInput);
   if(!TemplateEngine::hasFailed($pongsTpl))
   {
      for($i = 0; $i < count($pongsTpl); $i++)
         $finalTplInput['pongs'] .= $pongsTpl[$i];
   }
   else
      WebpageHandler::wrap($pongsTpl, 'Une erreur est survenue lors de l\'accès à la discussion');
   
   // Reply form
   $dialogs = '';
   if($discussion->get('state') === 'archived')
   {
      $otherUser = $discussion->get('emitter');
      if(LoggedUser::$data['pseudo'] === $otherUser)
         $otherUser = $discussion->get('receiver');
      
      $noFormTplInput = array('otherParty' => $otherUser, 'showLink' => '');
      $noFormTpl = TemplateEngine::parse('view/user/DiscussionReply.fail.ctpl', $noFormTplInput);
      if(!TemplateEngine::hasFailed($noFormTpl))
         $finalTplInput['newPongForm'] = $noFormTpl;
      else
         WebpageHandler::wrap($noFormTpl, 'Une erreur est survenue lors de l\'accès à la discussion');
   }
   else
   {
      $formTplInput = array('otherParty' => $discussion->get('emitter'),
      'showTitle' => '', 
      'errors' => '', 
      'discussionID' => $discussion->get('id_ping'), 
      'content' => '', 
      'toArchive' => '',
      'formEnd' => 'askAdvancedMode');
      
      if(LoggedUser::$data['pseudo'] === $discussion->get('emitter'))
         $formTplInput['otherParty'] = $discussion->get('receiver');

      // Dialogs for formatting
      $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
      if(!TemplateEngine::hasFailed($formattingDialogsTpl))
         $dialogs = $formattingDialogsTpl;
      
      $formTpl = TemplateEngine::parse('view/user/DiscussionReply.form.ctpl', $formTplInput);
      if(!TemplateEngine::hasFailed($formTpl))
         $finalTplInput['newPongForm'] = $formTpl;
      else
         WebpageHandler::wrap($formTpl, 'Une erreur est survenue lors de l\'accès à la discussion');
   }
   
   // Webpage settings
   WebpageHandler::addCSS('ping');
   if(WebpageHandler::$miscParams['message_size'] === 'medium')
      WebpageHandler::addCSS('ping_medium');
   WebpageHandler::addJS('formatting');
   WebpageHandler::addJS('ping_interaction');
   WebpageHandler::addJS('jquery.visible');
   WebpageHandler::addJS('pages');
   WebpageHandler::addJS('refresh');
   WebpageHandler::addJS('quick_preview');
   WebpageHandler::changeContainer('pingsContent');
   
   // Generates the whole page
   $display = TemplateEngine::parse('view/user/PrivateDiscussion.composite.ctpl', $finalTplInput);
   WebpageHandler::wrap($display, 'Discussion: '.$discussion->get('title').'', $dialogs);
}
else
{
   $tpl = TemplateEngine::parse('view/user/PrivateDiscussion.fail.ctpl', array('error' => 'wrongURL'));
   WebpageHandler::wrap($tpl, 'Discussion introuvable');
}

?>

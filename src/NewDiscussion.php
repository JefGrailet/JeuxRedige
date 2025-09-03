<?php

/**
* This script is designed to allow a logged in user to create a new private discussion with 
* another user through the ping system.
*/

require './libraries/Header.lib.php';

WebpageHandler::redirectionAtLoggingIn();
WebpageHandler::noRedirectionAtLoggingOut();

// User must be logged in
if(!LoggedUser::isLoggedIn())
{
   $tpl = TemplateEngine::parse('view/user/NotLoggedIn.ctpl');
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

require './model/Ping.class.php';
require './model/PingPong.class.php';
require './model/User.class.php';
require './model/Emoticon.class.php';
require './libraries/FormParsing.lib.php';

// Webpage settings
WebpageHandler::addCSS('ping');
if(WebpageHandler::$miscParams['message_size'] === 'medium')
   WebpageHandler::addCSS('ping_medium');
WebpageHandler::addCSS('preview');
WebpageHandler::addJS('formatting');
WebpageHandler::addJS('ping_recipient_selection');
WebpageHandler::addJS('preview');
WebpageHandler::changeContainer('pingsContent');

// Dialogs for formatting
$dialogs = '';
$formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   $dialogs = $formattingDialogsTpl;

// Array which serves both for template input and collecting $_POST values
$formData = array('errors' => '',
'recipientSelection' => 'missing',
'title' => '',
'content' => '',
'formEnd' => 'default',
'recipient' => '');

if(!empty($_POST['sent']))
{
   $formData['title'] = Utils::secure($_POST['title']);
   $formData['content'] = Utils::secure($_POST['message']);
   $formData['recipient'] = Utils::secure($_POST['recipient']);
   
   // Gets the delay between current time and the lattest discussion created by this user
   $delay = 3600;
   try
   {
      $delay = PingPong::getUserDelay();
   }
   catch(Exception $e) { }
   
   // Checking recipient
   if(strlen($formData['recipient']) > 0)
   {
      // Gets the existence of user
      $userExists = false;
      try
      {
         $userExists = User::userExists($formData['recipient']);
      }
      catch(Exception $e)
      {
         $formData['errors'] .= 'dbError';
      }
      
      if(!$userExists)
      {
         $formData['errors'] .= 'missingRecipient|';
         $formData['recipientSelection'] = 'missing';
      }
      else if($formData['recipient'] === LoggedUser::$data['pseudo'])
      {
         $formData['errors'] .= 'talkingToOneself|';
         $formData['recipientSelection'] = 'missing';
      }
      else
      {
         $formData['recipientSelection'] = 'selected||'.$formData['recipient'];
      }
   }
   else
   {
      $formData['errors'] .= 'emptyRecipient|';
      $formData['recipientSelection'] = 'missing';
   }
   
   if($_POST['sent'] == 'Mode avancé')
   {
      $formData['errors'] = ''; // Will remove emptyRecipient| if there
      $finalTpl = TemplateEngine::parse('view/user/NewDiscussion.form.ctpl', $formData);
      $finalTpl = WebpageHandler::wrapInBlock($finalTpl, 'plainBlock');
      WebpageHandler::wrap($finalTpl, 'Lancer une nouvelle discussion', $dialogs);
   }
   
   if(strlen($formData['title']) == 0 OR strlen($formData['content']) == 0)
      $formData['errors'] .= 'emptyMessage|';
   else if(strlen($formData['title']) > 50)
      $formData['errors'] .= 'titleTooLong|';
   
   // User created a new discussion less than 3 minutes ago
   if($delay < WebpageHandler::$miscParams['consecutive_pings_delay'])
      $formData['errors'] .= 'tooManyPings|';
   
   if(strlen($formData['errors']) == 0)
   {
      // Creates the discussion
      try
      {
         $parsedContent = Emoticon::parseEmoticonsShortcuts($formData['content']);
         $parsedContent = FormParsing::parse($parsedContent);
      
         $newPing = PingPong::insert($formData['recipient'], 
                                     $formData['title'], 
                                     $parsedContent);
      }
      catch(Exception $e)
      {
         $formData['errors'] = 'dbError';
         $finalTpl = TemplateEngine::parse('view/user/NewDiscussion.form.ctpl', $formData);
         $finalTpl = WebpageHandler::wrapInBlock($finalTpl, 'plainBlock');
         WebpageHandler::wrap($finalTpl, 'Lancer une nouvelle discussion', $dialogs);
      }
      
      // Redirection to the new discussion
      $newDiscussionURL = './PrivateDiscussion.php?id_ping='.$newPing->get('id_ping');
      header('Location:'.$newDiscussionURL);
      
      // Success page
      $tplInput = array('target' => $newDiscussionURL);
      $successPage = TemplateEngine::parse('view/user/NewDiscussion.success.ctpl', $tplInput);
      WebpageHandler::resetDisplay();
      WebpageHandler::wrap($successPage, 'Lancer une nouvelle discussion');
   }
   else
   {
      $formData['errors'] = substr($formData['errors'], 0, -1);
      $finalTpl = TemplateEngine::parse('view/user/NewDiscussion.form.ctpl', $formData);
      $finalTpl = WebpageHandler::wrapInBlock($finalTpl, 'plainBlock');
      WebpageHandler::wrap($finalTpl, 'Lancer une nouvelle discussion', $dialogs);
   }
}
else
{
   $finalTpl = TemplateEngine::parse('view/user/NewDiscussion.form.ctpl', $formData);
   $finalTpl = WebpageHandler::wrapInBlock($finalTpl, 'plainBlock');
   WebpageHandler::wrap($finalTpl, 'Lancer une nouvelle discussion', $dialogs);
}
   
?>

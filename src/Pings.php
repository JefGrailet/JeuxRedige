<?php

/**
* This script is used to display the pings of a given user.
*/

require './libraries/Header.lib.php';
require './model/Ping.class.php';
require './view/intermediate/Ping.ir.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('error' => 'login');
   $tpl = TemplateEngine::parse('view/user/Pings.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Dialogs for formatting (new discussion form)
$dialogs = '';
$dialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
if(!TemplateEngine::hasFailed($dialogsTpl))
   $dialogs = $dialogsTpl;

// Prepares the input for the final page
$newPingFormTplInput = array('errors' => '', 
                             'recipientSelection' => 'missing', 
                             'title' => '', 
                             'content' => '', 
                             'formEnd' => 'askAdvancedMode',
                             'recipient' => '');
$newPingFormTpl = TemplateEngine::parse('view/user/NewDiscussion.form.ctpl', $newPingFormTplInput);
$finalTplInput = array('pageConfig' => '',
'pings' => '',
'newPingForm' => $newPingFormTpl);

// Retrieves user's pings if possible; stops and displays appropriate error message otherwise
$pings = null;
try
{
   $nbPings = Ping::countPings();

   // Gets current page and computes the first index to retrieve the messages (or posts)
   $currentPage = 1;
   $nbPages = ceil($nbPings / WebpageHandler::$miscParams['posts_per_page']);
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

   $finalTplInput['pageConfig'] = WebpageHandler::$miscParams['posts_per_page'].'|'.$nbPings.'|'.$currentPage;
   $finalTplInput['pageConfig'] .= '|./Pings.php?page=[]';
   $pings = Ping::getPings($firstPost, WebpageHandler::$miscParams['posts_per_page']);
}
catch(Exception $e)
{
   // Problematic exceptions: everything besides "Ping could not be found"
   if(strstr($e->getMessage(), 'Ping could not be found') == FALSE)
   {
      $errorTplInput = array('error' => 'dbError');
      $tpl = TemplateEngine::parse('view/user/Pings.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible de lister les pings');
   }
}

// Webpage settings
WebpageHandler::addCSS('ping');
if(WebpageHandler::$miscParams['message_size'] === 'medium')
   WebpageHandler::addCSS('ping_medium');
WebpageHandler::addCSS('preview');
WebpageHandler::addJS('formatting');
WebpageHandler::addJS('ping_interaction');
WebpageHandler::addJS('ping_recipient_selection');
WebpageHandler::addJS('preview');
WebpageHandler::changeContainer('pingsContent');

// Some alternative display (no post to show)
if($pings == NULL)
{
   $tpl = TemplateEngine::parse('view/user/Pings.empty.ctpl', array('newPingForm' => $newPingFormTpl));
   WebpageHandler::wrap($tpl, 'Mes pings', $dialogs);
}

// Format pings
$fullInput = array();
for($i = 0; $i < count($pings); $i++)
{
   $pingIR = PingIR::process($pings[$i]);
   array_push($fullInput, $pingIR);
}

$pingsTpl = TemplateEngine::parseMultiple('view/user/Ping.ctpl', $fullInput);
if(!TemplateEngine::hasFailed($pingsTpl))
{
   for($i = 0; $i < count($pingsTpl); $i++)
      $finalTplInput['pings'] .= MessageParsing::parse($pingsTpl[$i], $i + 1);
}
else
   WebpageHandler::wrap($pingsTpl, 'Une erreur est survenue lors de la lecture des pings');

// Updates the views
try
{
   Ping::updateAllViews();
}
catch(Exception $e) { }

// Generates the whole page
$display = TemplateEngine::parse('view/user/Pings.ctpl', $finalTplInput);
WebpageHandler::wrap($display, 'Mes pings', $dialogs);

?>

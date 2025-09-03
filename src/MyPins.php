<?php

/**
* User page to handle pins.
*/

require './libraries/Header.lib.php';
require './model/Pin.class.php';
require './model/User.class.php';
require './view/intermediate/Pin.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in
if(!LoggedUser::isLoggedIn())
{
   $errorTplInput = array('error' => 'login');
   $tpl = TemplateEngine::parse('view/user/Pings.fail.ctpl', $errorTplInput); // Can be safely re-used, no ambiguity
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addJS('pins');
WebpageHandler::noContainer();

$pageTitle = 'Mes messages favoris';

// Gets the pins according to the current page (also deals with possible errors)
$nbPins = 0;
$pins = null;
try
{
   $nbPins = Pin::countPins();

   if($nbPins == 0)
   {
      $tpl = TemplateEngine::parse('view/user/PinsList.fail.ctpl', array('error' => 'noPin'));
      WebpageHandler::wrap($tpl, $pageTitle);
   }
   
   $currentPage = 1;
   $nbPages = ceil($nbPins / WebpageHandler::$miscParams['pins_per_page']);
   $firstPin = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstPin = ($getPage - 1) * WebpageHandler::$miscParams['pins_per_page'];
      }
   }
   $pins = Pin::getPins($firstPin, WebpageHandler::$miscParams['pins_per_page']);
}
catch(Exception $e)
{
   $tpl = TemplateEngine::parse('view/user/PinsList.fail.ctpl', array('error' => 'dbError'));
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les messages favoris');
}

// Intermediate interpretation of pins
$renderedPins = '';
$fullInput = array();
for($i = 0; $i < count($pins); $i++)
{
   $intermediate = PinIR::process($pins[$i]);
   array_push($fullInput, $intermediate);
}

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/user/Pin.item.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $tpl = TemplateEngine::parse('view/user/PinsList.fail.ctpl', array('error' => 'wrongTemplating'));
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les messages favoris');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $renderedPins .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['pins_per_page'].'|'.$nbPins.'|'.$currentPage;
$pageConfig .= '|./MyPins.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'pins' => $renderedPins);
$content = TemplateEngine::parse('view/user/PinsList.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, $pageTitle);

?>

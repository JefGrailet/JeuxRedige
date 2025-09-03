<?php

/**
* Admin page to view all alerts and details about the related posts.
*/

require './libraries/Header.lib.php';
require './model/Alert.class.php';
require './model/User.class.php';
require './view/intermediate/Alert.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not allowed to edit others' posts
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput); // Can be safely re-used, no ambiguity
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
if(!Utils::check(LoggedUser::$data['can_edit_all_posts']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/user/EditUser.fail.ctpl', $tplInput); // Can be safely re-used, no ambiguity
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::noContainer();

$pageTitle = 'Alertes des utilisateurs';

// Gets the alerts according to the current page (also deals with possible errors)
$nbAlerts = 0;
$alerts = null;
try
{
   $nbAlerts = Alert::countAlerts();

   if($nbAlerts == 0)
   {
      $tpl = TemplateEngine::parse('view/user/AlertsList.fail.ctpl', array('error' => 'noAlert'));
      WebpageHandler::wrap($tpl, $pageTitle);
   }
   
   $currentPage = 1;
   $nbPages = ceil($nbAlerts / WebpageHandler::$miscParams['pins_per_page']); // We re-use pins_per_page, since it's roughly the same display
   $firstAlert = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstAlert = ($getPage - 1) * WebpageHandler::$miscParams['pins_per_page'];
      }
   }
   $alerts = Alert::getAlerts($firstAlert, WebpageHandler::$miscParams['pins_per_page']);
   Alert::getPostDetails($alerts);
}
catch(Exception $e)
{
   $tpl = TemplateEngine::parse('view/user/AlertsList.fail.ctpl', array('error' => 'dbError'));
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les alertes');
}

// Intermediate interpretation of alerts
$renderedAlerts = '';
$fullInput = array();
for($i = 0; $i < count($alerts); $i++)
{
   $intermediate = AlertIR::process($alerts[$i]);
   array_push($fullInput, $intermediate);
}

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/user/Alert.item.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $tpl = TemplateEngine::parse('view/user/AlertsList.fail.ctpl', array('error' => 'wrongTemplating'));
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les alertes');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $renderedAlerts .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['pins_per_page'].'|'.$nbAlerts.'|'.$currentPage;
$pageConfig .= '|./Alerts.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'alerts' => $renderedAlerts);
$content = TemplateEngine::parse('view/user/AlertsList.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, $pageTitle);

?>

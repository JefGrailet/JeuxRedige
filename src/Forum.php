<?php

/**
* Main page of the forum.
*/

require './libraries/Header.lib.php';
require './model/Topic.class.php';
require './model/User.class.php';
require './view/intermediate/TopicThumbnail.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::noContainer();

// Filter
$filter = 'regular';
if(!empty($_GET['filter']))
{
   switch($_GET['filter'])
   {
      case 'favorites':
         $filter = 'favorites';
         break;
      default:
         break;
   }
}

// Prepares the common input for the templates that can be used
$commonTplInput = array('wholeList' => '',
'favorites' => '',
'research' => 'link');
if(LoggedUser::isLoggedIn())
{
   if($filter === 'favorites')
   {
      $commonTplInput['wholeList'] = 'link';
      $commonTplInput['favorites'] = 'viewed';
   }
   else
   {
      $commonTplInput['wholeList'] = 'viewed';
      $commonTplInput['favorites'] = 'link';
   }
}

// Connection of the user and associated object (useful for favorited topics)
$user = NULL;
if(LoggedUser::isLoggedIn())
   $user = new User(LoggedUser::$fullData);

// Gets the topics according to the current page and filter (also deals with possible errors)
$nbTopics = 0;
try
{
   if(LoggedUser::isLoggedIn() && $filter === 'favorites')
      $nbTopics = $user->countFavoritedTopics();
   else
      $nbTopics = Topic::countTopics();

   if($nbTopics == 0)
   {
      $errorTplInput = array_merge(array('error' => 'noTopic'), $commonTplInput);
      $tpl = TemplateEngine::parse('view/content/TopicsList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Aucun sujet n\'a été trouvé');
   }
   
   $currentPage = 1;
   $nbPages = ceil($nbTopics / WebpageHandler::$miscParams['topics_per_page']);
   $firstTopic = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstTopic = ($getPage - 1) * WebpageHandler::$miscParams['topics_per_page'];
      }
   }
   
   $favorited = NULL; // Stays NULL if the user is not logged OR if filter is set to "favorites"
   if(LoggedUser::isLoggedIn() && $filter === 'favorites')
   {
      $topics = $user->getFavoritedTopics($firstTopic, WebpageHandler::$miscParams['topics_per_page']);
   }
   else
   {
      $topics = Topic::getTopics($firstTopic, WebpageHandler::$miscParams['topics_per_page']);
      if(LoggedUser::isLoggedIn())
         Topic::getUserViews($topics);
   }
}
catch(Exception $e)
{
   $errorTplInput = array_merge(array('error' => 'dbError'), $commonTplInput);
   $tpl = TemplateEngine::parse('view/content/TopicsList.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les sujets');
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of thumbnails. */

// Rendered thumbnails
$thumbnails = '';
$fullInput = array();
for($i = 0; $i < count($topics); $i++)
{
   $intermediate = TopicThumbnailIR::process($topics[$i]);
   array_push($fullInput, $intermediate);
}

if(count($fullInput) > 0)
{
   $fullOutput = TemplateEngine::parseMultiple('view/content/TopicThumbnail.ctpl', $fullInput);
   if(TemplateEngine::hasFailed($fullOutput))
   {
      $errorTplInput = array_merge(array('error' => 'wrongTemplating'), $commonTplInput);
      $tpl = TemplateEngine::parse('view/content/TopicsList.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Impossible d\'atteindre les sujets');
   }

   for($i = 0; $i < count($fullOutput); $i++)
      $thumbnails .= $fullOutput[$i];
}

// Final HTML code (with page configuration)
$pageConfig = WebpageHandler::$miscParams['topics_per_page'].'|'.$nbTopics.'|'.$currentPage;
$pageConfig .= '|./Forum.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails);
$finalTplInput = array_merge($finalTplInput, $commonTplInput);
$content = TemplateEngine::parse('view/content/TopicsList.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Forum');

?>

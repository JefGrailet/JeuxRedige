<?php

/**
* Homepage of a given game (provided in URL). It consists of a game header along a list of all
* content related to the game (articles tagged with the game, topics tagged with the game, etc.). 
* There are two modes of display:
* 1) default display: the page displays a bit of everything, but with limited quantities and a 
*    button for the second mode (see below) when the amount of items is above the threshold.
* 2) selected display: the page displays only a category of content with pagination
*/

require './libraries/Header.lib.php';
require './model/Game.class.php';
require './view/intermediate/GameHeader.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Checks a game is mentioned in the URL
if(!isset($_GET['game']))
{
   $tpl = TemplateEngine::parse('view/content/GameHome.fail.ctpl', array('error' => 'wrongURL'));
   WebpageHandler::wrap($tpl, 'Impossible de trouver le jeu');
}
$gameTitle = Utils::secure(urldecode($_GET['game']));

// Gets the game and its main details
$game = null;
try
{
   $game = new Game($gameTitle);
   $aliases = $game->getAliases();
}
catch(Exception $e)
{
   $tplInput = array('error' => 'dbError');
   if(strstr($e->getMessage(), 'does not exist') != FALSE)
      $tplInput['error'] = 'missingGame';
   $tpl = TemplateEngine::parse('view/content/GameHome.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Jeu introuvable');
}

// Main display settings
WebpageHandler::addCSS('pool');
WebpageHandler::addCSS('media');
WebpageHandler::addCSS('game_header');
WebpageHandler::addCSS('game_content');
WebpageHandler::addJS('commentables');
WebpageHandler::noContainer();

// Preparing game header (displayed no matter what happens)
$headerTplInput = GameHeaderIR::process($game->getAll(), $aliases);
$headerTpl = TemplateEngine::parse('view/content/GameHeader.ctpl', $headerTplInput);
if(!TemplateEngine::hasFailed($headerTpl))
   $finalTplInput['header'] = $headerTpl;
else
   WebpageHandler::wrap($headerTpl, 'Une erreur est survenue lors de la lecture du jeu');

// Finds which display the user has chosen to see what libraries are relevant
$displayMode = 'default';
$modes = array('articles', 'trivia', 'lists', 'topics', 'default');
if(!empty($_GET['section']))
{
   $selectedMode = Utils::secure($_GET['section']);
   if(in_array($selectedMode, $modes))
      $displayMode = $selectedMode;
}

// Fetches the content for each section
$rArticles = '';
$rTrivia = '';
$rLists = '';
$rTopics = '';
if($displayMode === 'default' || $displayMode === 'articles')
{
   require './model/Article.class.php';
   require './view/intermediate/ArticleThumbnail.ir.php';
   
   try
   {
      // Counts related topics
      $needle = array($gameTitle);
      $nbRelated = Article::countArticlesWithKeywords($needle);
      // No display at all if there's no article
      if($nbRelated > 0)
      {
         $tplInput = array('thumbnails' => '', 'pageConfig' => '', 'allArticles' => '');
         $perPage = WebpageHandler::$miscParams['articles_per_page'];
         $firstArt = 0;
         
         // Pagination only makes sense on dedicated section and if there are enough topics
         if($displayMode === 'articles')
         {
            $currentPage = 1;
            $nbPages = ceil($nbRelated / $perPage);
            if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
            {
               $getPage = intval($_GET['page']);
               if($getPage <= $nbPages)
               {
                  $currentPage = $getPage;
                  $firstArt = ($getPage - 1) * $perPage;
               }
            }
            
            $tplInput['pageConfig'] = $perPage.'|'.$nbRelated.'|'.$currentPage;
            $tplInput['pageConfig'] .= '|'.PathHandler::gameURL($game->getAll(), 'articles', '[]');
         }
         else if($perPage < $nbRelated)
         {
            $tplInput['allArticles'] = 'yes||'.PathHandler::gameURL($game->getAll(), 'articles', '').'|'.$nbRelated;
         }
         
         // Now retrieving the topics
         $results = Article::getArticlesWithKeywords($needle, $firstArt, $perPage);
         
         // Rendering the thumbnails
         $input = array();
         for($i = 0; $i < count($results); $i++)
            array_push($input, ArticleThumbnailIR::process($results[$i]));
         
         if(count($input) > 0)
         {
            $output = TemplateEngine::parseMultiple('view/content/ArticleThumbnail.ctpl', $input);
            if(TemplateEngine::hasFailed($output))
            {
               $tplInput = array('error' => 'badTemplating');
               $rArticles = TemplateEngine::parse('view/content/RelatedArticles.fail.ctpl', $tplInput);
            }
            else
            {
               for($i = 0; $i < count($output); $i++)
                  $tplInput['thumbnails'] .= $output[$i];
               $rArticles = TemplateEngine::parse('view/content/RelatedArticles.ctpl', $tplInput);
            }
         }
      }
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      $rArticles = TemplateEngine::parse('view/content/RelatedArticles.fail.ctpl', $tplInput);
   }
}
if($displayMode === 'default' || $displayMode === 'trivia')
{
   require './model/Trivia.class.php';
   require './view/intermediate/Trivia.ir.php';
   require_once './libraries/MessageParsing.lib.php';
   
   // Additional JS
   WebpageHandler::addJS('trivia_interaction');
   
   try
   {
      // Counts related pieces of trivia
      $needle = $gameTitle;
      $nbRelated = Trivia::countPieces($needle);
      
      if($nbRelated == 0)
      {
         // Will display something only if the section is empty (so user can add a new piece)
         if(LoggedUser::isLoggedIn())
         {
            $tplInput = array('error' => 'noPiece', 'gameTitle' => urlencode($gameTitle));
            $rTrivia = TemplateEngine::parse('view/content/RelatedTrivia.fail.ctpl', $tplInput);
         }
      }
      else
      {
         // Overall template input
         $tplInput = array('pieces' => '', 
                           'pageConfig' => '', 
                           'allTrivia' => '', 
                           'newPiece' => '');
         
         if(LoggedUser::isLoggedIn())
            $tplInput['newPiece'] = 'yes||'.urlencode($gameTitle);
         
         $perPage = WebpageHandler::$miscParams['topics_per_page'];
         $firstPiece = 0;
         
         // Pagination only makes sense on dedicated section and if there are enough pieces
         if($displayMode === 'trivia')
         {
            $currentPage = 1;
            $nbPages = ceil($nbRelated / $perPage);
            if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
            {
               $getPage = intval($_GET['page']);
               if($getPage <= $nbPages)
               {
                  $currentPage = $getPage;
                  $firstPiece= ($getPage - 1) * $perPage;
               }
            }
            
            $tplInput['pageConfig'] = $perPage.'|'.$nbRelated.'|'.$currentPage;
            $tplInput['pageConfig'] .= '|'.PathHandler::gameURL($game->getAll(), 'trivia', '[]');
         }
         // If not on the dedicated page, then only the 5 last pieces are displayed
         else if($nbRelated > 5)
         {
            $perPage = 5;
            $tplInput['allTrivia'] = 'yes||'.PathHandler::gameURL($game->getAll(), 'trivia').'|'.$nbRelated;
         }
         
         // Now retrieving the pieces themselves
         $piecesArr = Trivia::getPieces($firstPiece, $perPage, $needle);
         $pieces = array();
         for($i = 0; $i < count($piecesArr); $i++)
         {
            $piecesArr[$i]['content'] = MessageParsing::parse($piecesArr[$i]['content'], ($i + 1));
            array_push($pieces, new Trivia($piecesArr[$i]));
         }
         
         // Rendering the pieces
         $input = array();
         for($i = 0; $i < count($pieces); $i++)
            array_push($input, TriviaIR::process($pieces[$i]));
         $output = TemplateEngine::parseMultiple('view/content/Trivia.ctpl', $input);
         
         // Displays the final pieces
         if(TemplateEngine::hasFailed($output))
         {
            $tplInput = array('error' => 'badTemplating', 'gameTitle' => urlencode($gameTitle));
            $rTrivia = TemplateEngine::parse('view/content/RelatedTrivia.fail.ctpl', $tplInput);
         }
         else
         {
            for($i = 0; $i < count($output); $i++)
               $tplInput['pieces'] .= $output[$i];
            $rTrivia = TemplateEngine::parse('view/content/RelatedTrivia.ctpl', $tplInput);
         }
      }
   }
   catch(Exception $e)
   {
      echo $e->getMessage().'<br/>';
      $tplInput = array('error' => 'dbError', 'gameTitle' => urlencode($gameTitle));
      $rTrivia = TemplateEngine::parse('view/content/RelatedTrivia.fail.ctpl', $tplInput);
   }
}
if($displayMode === 'default' || $displayMode === 'lists')
{
   require './model/GamesList.class.php';
   
   try
   {
      $nbRelated = GamesList::countListsMentioning($gameTitle);
      
      // Something will be displayed (besides bad DB reading) if and only if there are lists
      if($nbRelated > 0)
      {
         require './view/intermediate/ListThumbnail.ir.php';
         
         $tplInput = array('thumbnails' => '',
         'pageConfig' => '',
         'allLists' => '');
         
         $perPage = WebpageHandler::$miscParams['topics_per_page'];
         $firstList = 0;
         
         // Pagination only makes sense on dedicated section and if there are enough lists
         if($displayMode === 'lists')
         {
            $currentPage = 1;
            $nbPages = ceil($nbRelated / $perPage);
            if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
            {
               $getPage = intval($_GET['page']);
               if($getPage <= $nbPages)
               {
                  $currentPage = $getPage;
                  $firstList = ($getPage - 1) * $perPage;
               }
            }
            
            $tplInput['pageConfig'] = $perPage.'|'.$nbRelated.'|'.$currentPage;
            $tplInput['pageConfig'] .= '|'.PathHandler::gameURL($game->getAll(), 'lists', '[]');
         }
         else if($perPage < $nbRelated)
         {
            $tplInput['allLists'] = 'yes||'.PathHandler::gameURL($game->getAll(), 'lists', '').'|'.$nbRelated;
         }
         
         // Now retrieving and rendering the lists as thumbnails
         $results = GamesList::getListsMentioning($gameTitle, $firstList, $perPage);
         $input = array();
         for($i = 0; $i < count($results); $i++)
         {
            $intermediate = ListThumbnailIR::process($results[$i]);
            array_push($input, $intermediate);
         }
         
         if(count($input) > 0)
         {
            $output = TemplateEngine::parseMultiple('view/content/ListThumbnail.ctpl', $input);
            if(TemplateEngine::hasFailed($output))
            {
               $tplInput = array('error' => 'badTemplating');
               $rLists = TemplateEngine::parse('view/content/RelatedLists.fail.ctpl', $tplInput);
            }
            else
            {
               for($i = 0; $i < count($output); $i++)
                  $tplInput['thumbnails'] .= $output[$i];
                  
               $rLists = TemplateEngine::parse('view/content/RelatedLists.ctpl', $tplInput);
            }
         }
      }
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      $rTopics = TemplateEngine::parse('view/content/RelatedLists.fail.ctpl', $tplInput);
   }
}
if($displayMode === 'default' || $displayMode === 'topics')
{
   require './model/Topic.class.php';
   require './view/intermediate/TopicThumbnail.ir.php';
   
   try
   {
      // Counts related topics
      $needle = array($gameTitle);
      $nbRelated = Topic::countTopicsWithKeywords($needle);
      if($nbRelated == 0)
      {
         $tplInput = array('error' => 'noTopic', 'gameTitle' => urlencode($gameTitle));
         $rTopics = TemplateEngine::parse('view/content/RelatedTopics.fail.ctpl', $tplInput);
      }
      else
      {
         $tplInput = array('thumbnails' => '',
         'pageConfig' => '',
         'allTopics' => '',
         'gameTitle' => urlencode($gameTitle));
         
         $perPage = WebpageHandler::$miscParams['topics_per_page'];
         $firstTopic = 0;
         
         // Pagination only makes sense on dedicated section and if there are enough topics
         if($displayMode === 'topics')
         {
            $currentPage = 1;
            $nbPages = ceil($nbRelated / $perPage);
            if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
            {
               $getPage = intval($_GET['page']);
               if($getPage <= $nbPages)
               {
                  $currentPage = $getPage;
                  $firstTopic = ($getPage - 1) * $perPage;
               }
            }
            
            $tplInput['pageConfig'] = $perPage.'|'.$nbRelated.'|'.$currentPage;
            $tplInput['pageConfig'] .= '|'.PathHandler::gameURL($game->getAll(), 'topics', '[]');
         }
         else if($perPage < $nbRelated)
         {
            $tplInput['allTopics'] = 'yes||'.PathHandler::gameURL($game->getAll(), 'topics', '').'|'.$nbRelated;
         }
         
         // Now retrieving the topics
         $results = Topic::getTopicsWithKeywords($needle, $firstTopic, $perPage);
         $favorited = NULL;
         if(LoggedUser::isLoggedIn())
            Topic::getUserViews($results);
         
         // Rendering the thumbnails
         $input = array();
         for($i = 0; $i < count($results); $i++)
         {
            $intermediate = TopicThumbnailIR::process($results[$i]);
            array_push($input, $intermediate);
         }
         
         if(count($input) > 0)
         {
            $output = TemplateEngine::parseMultiple('view/content/TopicThumbnail.ctpl', $input);
            if(TemplateEngine::hasFailed($output))
            {
               $tplInput = array('error' => 'badTemplating', 'gameTitle' => urlencode($gameTitle));
               $rTopics = TemplateEngine::parse('view/content/RelatedTopics.fail.ctpl', $tplInput);
            }
            else
            {
               for($i = 0; $i < count($output); $i++)
                  $tplInput['thumbnails'] .= $output[$i];
                  
               $rTopics = TemplateEngine::parse('view/content/RelatedTopics.ctpl', $tplInput);
            }
         }
      }
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError', 'gameTitle' => urlencode($gameTitle));
      $rTopics = TemplateEngine::parse('view/content/RelatedTopics.fail.ctpl', $tplInput);
   }
}

if($displayMode === 'default')
{
   if(strlen($rArticles) > 0)
      $rArticles .= "\n<br/>\n";
}

// Final HTML page
WebpageHandler::wrap($headerTpl.$rArticles.$rTrivia.$rLists.$rTopics, $gameTitle);

?>

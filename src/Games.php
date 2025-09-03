<?php

/*
* Script to display the list of games registered in the DB, in alphabetical order. This list 
* should be accessible only to authorized people, for now.
*/

require './libraries/Header.lib.php';
require './model/Game.class.php';
require './view/intermediate/GameLight.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Temporar errors (this part will disappear after section becomes public)
if(!LoggedUser::isLoggedIn())
{
   header('Location:./index.php');
   $tplInput = array('error' => 'notConnected', 'searchButton' => 'yes', 'newGameButton' => '');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous devez être connecté');
}
/*
if(!Utils::check(LoggedUser::$data['can_edit_games']))
{
   header('Location:./index.php');
   $tplInput = array('error' => 'forbiddenAccess');
   $tpl = TemplateEngine::parse('view/content/EditGame.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Vous n\'êtes pas autorisé à utiliser cette page');
}
*/

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addCSS('media');
WebpageHandler::noContainer();

// Gets the amount of games and the games in the current page.
$nbGames = 0;
try
{
   $nbGames = Game::countGames();

   if($nbGames == 0)
   {
      $errorTplInput = array('error' => 'noGame', 'searchButton' => 'yes', 'newGameButton' => 'yes');
      $tpl = TemplateEngine::parse('view/content/Games.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Liste des jeux');
   }
   
   $currentPage = 1;
   $perPage = WebpageHandler::$miscParams['topics_per_page'];
   $nbPages = ceil($nbGames / $perPage);
   $firstGame = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstGame = ($getPage - 1) * $perPage;
      }
   }
   
   $games = Game::getGames($firstGame, $perPage);
}
catch(Exception $e)
{
   $errorTplInput = array('error' => 'dbError', 'searchButton' => 'yes', 'newGameButton' => 'yes');
   $tpl = TemplateEngine::parse('view/content/Games.fail.ctpl', $errorTplInput);
   WebpageHandler::wrap($tpl, 'Liste des jeux');
}

/* From this point, all the content has been extracted from the DB. All what is left to do is
* to render it as a pool of games. */

// Rendered game thumbnails
$thumbnails = '';
for($i = 0; $i < count($games); $i++)
{
   $intermediate = GameLightIR::process($games[$i], false);
   $thumbnail = TemplateEngine::parse('view/content/GameLight.ctpl', $intermediate);
   if(TemplateEngine::hasFailed($thumbnail))
   {
      $errorTplInput = array('error' => 'wrongTemplating', 'searchButton' => 'yes', 'newGameButton' => 'yes');
      $tpl = TemplateEngine::parse('view/content/Games.fail.ctpl', $errorTplInput);
      WebpageHandler::wrap($tpl, 'Liste des jeux');
   }
   $thumbnails .= $thumbnail;
}

// Final HTML code (with page configuration)
$pageConfig = $perPage.'|'.$nbGames.'|'.$currentPage;
$pageConfig .= '|./Games.php?page=[]';
$finalTplInput = array('pageConfig' => $pageConfig, 'thumbnails' => $thumbnails, 
                       'searchButton' => 'yes', 'newGameButton' => 'yes');
$content = TemplateEngine::parse('view/content/Games.ctpl', $finalTplInput);

// Displays the produced page
WebpageHandler::wrap($content, 'Liste des jeux');

?>

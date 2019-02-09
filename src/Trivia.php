<?php

/**
* This script shows a single piece of trivia. The only input is the ID of that piece.
*/

require './libraries/Header.lib.php';

require './model/Trivia.class.php';
require './view/intermediate/Trivia.ir.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['id_trivia']) && preg_match('#^([0-9]+)$#', $_GET['id_trivia']))
{
   $getID = intval($_GET['id_trivia']);
   
   // Gets the piece, its data and deals with errors if any.
   $trivia = NULL;
   try
   {
      $trivia = new Trivia($getID);
      $trivia->getTopic(); // Loads associated topic
      $trivia->getRatings();
      $trivia->getUserRating();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Une erreur est survenue');
   }
   
   // Redirects to right URL if $_GET['game'] and $_GET['title'] don't match
   if(!empty($_GET['game']) && !empty($_GET['title']))
   {
      $gameURL = Utils::secure($_GET['game']);
      $titleURL = Utils::secure($_GET['title']);
      $reformattedGame = PathHandler::formatForURL($trivia->get('game'));
      $reformattedTitle = PathHandler::formatForURL($trivia->get('title'));
      if($reformattedGame !== $gameURL || $reformattedTitle !== $titleURL)
         header('Location:'.PathHandler::reviewURL($trivia->getAll()));
      
      WebpageHandler::usingURLRewriting();
   }
   
   // Webpage settings
   WebpageHandler::addCSS('media');
   WebpageHandler::addCSS('game_content');
   WebpageHandler::addJS('trivia_interaction');
   WebpageHandler::addJS('commentables');
   WebpageHandler::noContainer();
   
   // Title of the page
   $windowTitle = 'Anecdote à propos de '.$trivia->get('game').': '.$trivia->get('title').' ';
   $windowTitle .= '(par '.$trivia->get('pseudo').')';
   
   // Formats the piece into an IR
   $trivia->set('content', MessageParsing::parse($trivia->get('content')));
   $intermediate = TriviaIR::process($trivia, true);
   
   // Generates the page, which consists of an encapsulated Trivia.ctpl
   $finalPiece = TemplateEngine::parse('view/content/Trivia.ctpl', $intermediate);
   WebpageHandler::wrap('<div id="triviaContainer">'."\n".$finalPiece."</div>\n", $windowTitle);
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le contenu à afficher est manquant');
}
?>

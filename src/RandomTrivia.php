<?php

/**
* This script shows a selection of random pieces of trivia.
*/

require './libraries/Header.lib.php';

require './model/Trivia.class.php';
require './view/intermediate/Trivia.ir.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

// Gets random pieces of trivia
$triviaArr = NULL;
try
{
   $triviaArr = Trivia::getRandomPieces(2); // For now
}
catch(Exception $e)
{
   $tplInput = array('error' => 'dbError');
   $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

// No trivia recorded yet
if($triviaArr == NULL)
{
   $tpl = TemplateEngine::parse('view/content/RandomTrivia.empty.ctpl');
   WebpageHandler::addCSS('pool');
   WebpageHandler::noContainer();
   WebpageHandler::wrap($tpl, 'Aucune anecdote à afficher');
}

// Formatting the pieces
$pieces = array();
for($i = 0; $i < count($triviaArr); $i++)
{
   $triviaArr[$i]['content'] = MessageParsing::parse($triviaArr[$i]['content'], ($i + 1));
   array_push($pieces, new Trivia($triviaArr[$i]));
}

// Rendering the pieces, with game thumbnail but asynchronous display for the rest
$input = array();
for($i = 0; $i < count($pieces); $i++)
   array_push($input, TriviaIR::addGameThumbnail($pieces[$i], TriviaIR::process($pieces[$i])));
$output = TemplateEngine::parseMultiple('view/content/Trivia.ctpl', $input);

// Prepares the final template
$finalTplInput = array('pieces' => '');
for($i = 0; $i < count($output); $i++)
   $finalTplInput['pieces'] .= $output[$i];

// Webpage settings
WebpageHandler::addCSS('pool');
WebpageHandler::addCSS('media');
WebpageHandler::addCSS('game_content');
WebpageHandler::addJS('trivia_interaction');
WebpageHandler::addJS('commentables');
WebpageHandler::noContainer();

// Renders the final page
$finalTpl = TemplateEngine::parse('view/content/RandomTrivia.ctpl', $finalTplInput);
WebpageHandler::wrap($finalTpl, 'Le saviez-vous ?');
?>

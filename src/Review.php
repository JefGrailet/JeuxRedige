<?php

/**
* This script shows a single review. The only input is the ID of that review.
*/

require './libraries/Header.lib.php';

require './model/Review.class.php';
require './model/Trope.class.php';
require './view/intermediate/Review.ir.php';
require './libraries/MessageParsing.lib.php';

WebpageHandler::redirectionAtLoggingIn();

if(!empty($_GET['id_review']) && preg_match('#^([0-9]+)$#', $_GET['id_review']))
{
   $getID = intval($_GET['id_review']);
   
   // Gets the review, its data and deals with errors if any.
   $review = NULL;
   $tropes = NULL;
   try
   {
      $review = new Review($getID);
      $tropes = $review->getTropes();
      $review->getArticle(); // Loads associated article data
      $review->getTopic(); // Ditto for associated topic
      $review->getRatings();
      $review->getUserRating();
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
      $reformattedGame = PathHandler::formatForURL($review->get('game'));
      $reformattedTitle = PathHandler::formatForURL($review->get('title'));
      if($reformattedGame !== $gameURL || $reformattedTitle !== $titleURL)
         header('Location:'.PathHandler::reviewURL($review->getAll()));
      
      WebpageHandler::usingURLRewriting();
   }
   
   // Title of the page
   $windowTitle = 'Evaluation de '.$review->get('game').': '.$review->get('title').' ';
   $windowTitle .= '(par '.$review->get('pseudo').')';
   
   // Formats the review into an IR
   $review->set('comment', MessageParsing::parse($review->get('comment')));
   $intermediate = ReviewIR::process($review, true);
   
   // Rendered tropes
   $thumbnails = '';
   if(count($tropes) > 0)
   {
      $tropesInput = array();
      for($i = 0; $i < count($tropes); $i++)
         array_push($tropesInput, TropeIR::process($tropes[$i]));
      $tropesOutput = TemplateEngine::parseMultiple('view/content/Trope.ctpl', $tropesInput);
      if(TemplateEngine::hasFailed($tropesOutput))
      {
         $errorTplInput = array('error' => 'wrongTemplating');
         $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $errorTplInput);
         WebpageHandler::wrap($tpl, $windowTitle);
      }
      else
      {
         for($i = 0; $i < count($tropesOutput); $i++)
            $thumbnails .= $tropesOutput[$i];
      }
   }
   
   // Webpage settings
   WebpageHandler::addCSS('media');
   WebpageHandler::addCSS('game_content');
   WebpageHandler::addJS('review_interaction');
   WebpageHandler::addJS('commentables');
   WebpageHandler::noContainer();
   
   // Generates the page
   $finalReview = TemplateEngine::parse('view/content/Review.ctpl', $intermediate);
   $finalTplInput = array('fullTropes' => $thumbnails, 'review' => $finalReview);
   
   $tpl = TemplateEngine::parse('view/content/SingleReview.ctpl', $finalTplInput);
   WebpageHandler::wrap($tpl, $windowTitle);
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Le contenu à afficher est manquant');
}
?>

<?php

/*
* Script to display a full article, whether it has been published online (full access for 
* everyone) or it is still being written (in this case, only the author can view it).
*/

require './libraries/Header.lib.php';
require './libraries/SegmentParsing.lib.php';
require './model/Article.class.php';
require './model/Segment.class.php';
require './view/intermediate/Article.ir.php';
require './view/intermediate/Segment.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Obtains game title and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   try
   {
      $article = new Article($articleID);
      $article->loadRelatedData();
      if($article->isPublished())
      {
         $article->getTopic();
         $article->incViews();
      }
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'nonexistingArticle';
      $tpl = TemplateEngine::parse('view/content/Article.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Article introuvable');
   }
   
   // Redirects to right URL if $_GET['title'] does not match
   if(!empty($_GET['title']))
   {
      $titleURL = Utils::secure($_GET['title']);
      if(PathHandler::formatForURL($article->get('title').' '.$article->get('subtitle')) !== $titleURL)
         header('Location:'.PathHandler::articleURL($article->getAll()));
      
      WebpageHandler::usingURLRewriting();
   }
   
   // No segment
   $segments = $article->getBufferedSegments();
   if(count($segments) == 0)
   {
      $tplInput = array('error' => 'noSegment');
      $tpl = TemplateEngine::parse('view/content/Article.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Impossible d\'afficher l\'article');
   }
   
   // Restricted view
   if(!$article->isPublished())
   {
      if((!LoggedUser::isLoggedIn()) || $article->get('pseudo') !== LoggedUser::$data['pseudo'])
      {
         $tplInput = array('error' => 'restrictedAccess');
         $tpl = TemplateEngine::parse('view/content/Article.fail.ctpl', $tplInput);
         WebpageHandler::wrap($tpl, 'Article en accès restreint');
      }
   }
   
   // Webpage settings
   WebpageHandler::$miscParams['webdesign_variant'] = $article->get('type'); // Changes the logo
   WebpageHandler::addCSS('article');
   WebpageHandler::addCSS('charter_'.$article->get('type')); // To comply with the charter colors
   WebpageHandler::addCSS('media');
   WebpageHandler::addJS('article');
   WebpageHandler::noContainer();
   
   // Pre-selected segment
   $selectedSegment = 1;
   if(!empty($_GET['section']))
   {
      $getSection = intval(Utils::secure($_GET['section']));
      if($getSection > 0 && $getSection <= count($segments))
         $selectedSegment = $getSection;
      else
         header('Location:'.PathHandler::articleURL($article->getAll()));
   }
   
   // Generates all useful data for article display
   $articleIR = ArticleIR::process($article, $selectedSegment);
   
   // Renders segments
   $fullInput = array();
   for($i = 0; $i < count($segments); $i++)
   {
      $segments[$i]['content'] = SegmentParsing::parse($segments[$i]['content'], $i + 1);
      $segmentIR = SegmentIR::process($segments[$i], (($i + 1) == $selectedSegment));
      array_push($fullInput, $segmentIR);
   }
   
   // Fixes title/subtitle on first segment
   if($segments[0]['title'] == NULL)
   {
      $fullInput[0]['title'] = $article->get('title');
      $fullInput[0]['mainSubtitle'] = 'yes||'.$article->get('subtitle');
   }
   
   $segmentsTpl = TemplateEngine::parseMultiple('view/content/Segment.ctpl', $fullInput);
   $segmentsStr = '';
   if(!TemplateEngine::hasFailed($segmentsTpl))
   {
      for($i = 0; $i < count($segmentsTpl); $i++)
         $segmentsStr .= $segmentsTpl[$i];
   }
   else
      WebpageHandler::wrap($segmentsTpl, 'Une erreur est survenue lors de la lecture des segments');
   
   // Meta-tags
   WebpageHandler::$miscParams['meta_title']= $article->get('title');
   WebpageHandler::$miscParams['meta_description']= $article->get('subtitle');
   WebpageHandler::$miscParams['meta_image']= $article->getThumbnail();
   WebpageHandler::$miscParams['meta_url']= "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
   
   // Display
   $finalTplInput = array_merge(array('segments' => $segmentsStr), $articleIR);
   $tpl = TemplateEngine::parse('view/content/Article.composite.ctpl', $finalTplInput);
   $chosenSubtitle = $article->get('subtitle');
   WebpageHandler::wrap($tpl, $article->get('title').' - '.$chosenSubtitle);
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/Article.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Article introuvable');
}
?>

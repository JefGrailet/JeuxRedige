<?php

/*
* Script to display a full list.
*/

require './libraries/Header.lib.php';
require './libraries/MessageParsing.lib.php';
require './model/GamesList.class.php';
require './model/Emoticon.class.php';
require './view/intermediate/List.ir.php';

WebpageHandler::redirectionAtLoggingIn();

// Obtains game title and retrieves the corresponding entry
if(!empty($_GET['id_list']) && preg_match('#^([0-9]+)$#', $_GET['id_list']))
{
   $listID = intval(Utils::secure($_GET['id_list']));
   $list = null;
   $items = null;
   try
   {
      $list = new GamesList($listID);
      $items = $list->getItems();
      
      $list->getTopic(); // Loads associated topic
      $list->getRatings();
      $list->getUserRating();
   }
   catch(Exception $e)
   {
      $tplInput = array('error' => 'dbError');
      if(strstr($e->getMessage(), 'does not exist') != FALSE)
         $tplInput['error'] = 'missingContent';
      $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $tplInput);
      WebpageHandler::wrap($tpl, 'Liste introuvable');
   }
   
   // Redirects to right URL if $_GET['title'] does not match
   if(!empty($_GET['title']))
   {
      $titleURL = Utils::secure($_GET['title']);
      if(PathHandler::formatForURL($list->get('title')) !== $titleURL)
         header('Location:'.PathHandler::listURL($list->getAll()));
      
      WebpageHandler::usingURLRewriting();
   }
   
   // Webpage settings
   WebpageHandler::addCSS('list');
   WebpageHandler::addJS('list_interaction');
   WebpageHandler::addJS('commentables');
   WebpageHandler::noContainer();
   
   // Generates all useful data for list display.
   for($i = 0; $i < count($items); $i++) // Still the buffered items
      $items[$i]['comment'] = MessageParsing::parse($items[$i]['comment'], $i + 1);
   $list->updateBufferedItems($items); // Makes sure the post-parsing is taken into account
   $listIR = ListIR::process($list);
   
   // Meta-tags (TODO: generalize)
   WebpageHandler::$miscParams['meta_title'] = $list->get('title');
   // WebpageHandler::$miscParams['meta_description'] = $list->get('subtitle');
   WebpageHandler::$miscParams['meta_image'] = $list->getThumbnail();
   WebpageHandler::$miscParams['meta_url']= "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
   
   // Display
   $tpl = TemplateEngine::parse('view/content/List.composite.ctpl', $listIR);
   WebpageHandler::wrap($tpl, $list->get('title'));
}
else
{
   $tplInput = array('error' => 'missingID');
   $tpl = TemplateEngine::parse('view/content/Content.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Liste introuvable');
}
?>

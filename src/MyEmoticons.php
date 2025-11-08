<?php

/**
* User page to handle and create new emoticons.
*/

require './libraries/Header.lib.php';
require './model/Emoticon.class.php';
require './model/User.class.php';
require './view/intermediate/EmoticonThumbnail.ir.php';

require_once './libraries/core/Twig.config.php';
// require_once './libraries/core/Utils.class.php';

WebpageHandler::redirectionAtLoggingIn();

// User must be logged in
if (!LoggedUser::isLoggedIn()) {
   http_response_code(401);
   echo $twig->render("error.html.twig", [
      "error_title" => "Page inaccessible",
      "error_key" => "notLogged",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur - Page inaccessible",
         "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
         "full_title" => "",
      ]
   ]);

   die();
}

// Filter
$filter = 'myEmoticons';
if(!empty($_GET['filter']))
{
   switch($_GET['filter'])
   {
      case 'global':
         $filter = 'library';
         break;
      default:
         break;
   }
}

// Prepares the common input for the templates that can be used
$commonTplInput = array('myEmoticons' => 'viewed',
'emoticonsLibrary' => 'link',
'newEmoticonDialog' => '');

if(Utils::check(LoggedUser::$data['can_upload']))
{
   $commonTplInput['newEmoticonDialog'] = 'yes';
}

$pageTitle = 'Mes émoticônes';
if($filter === 'library')
{
   $commonTplInput['myEmoticons'] = 'link';
   $commonTplInput['emoticonsLibrary'] = 'viewed';
   $pageTitle = 'Librairie d\'émoticônes';
}


// Gets the emoticons according to the current page and filter (also deals with possible errors)
$nbEmoticons = 0;
$emoticons = null;
$listEmoticons = [];
try
{
   if($filter === 'myEmoticons')
      $nbEmoticons = Emoticon::countMyEmoticons();
   else
      $nbEmoticons = Emoticon::countEmoticons();

   if($nbEmoticons == 0) {
      goto skip_emoticons_loading;
   }

   $currentPage = 1;
   $nbPages = ceil($nbEmoticons / WebpageHandler::$miscParams['emoticons_per_page']);
   $firstEmoticon = 0;
   if(!empty($_GET['page']) && preg_match('#^([0-9]+)$#', $_GET['page']))
   {
      $getPage = intval($_GET['page']);
      if($getPage <= $nbPages)
      {
         $currentPage = $getPage;
         $firstEmoticon = ($getPage - 1) * WebpageHandler::$miscParams['emoticons_per_page'];
      }
   }

   if($filter === 'myEmoticons')
      $listEmoticons = Emoticon::getMyEmoticons($firstEmoticon, WebpageHandler::$miscParams['emoticons_per_page']);
   else
      $listEmoticons = Emoticon::getEmoticons($firstEmoticon, WebpageHandler::$miscParams['emoticons_per_page']);
}
catch(Exception $e)
{
   $errorTplInput = array_merge(array('error' => 'dbError'), $commonTplInput);
   $tpl = TemplateEngine::parse('view/user/EmoticonsList.fail.ctpl', $errorTplInput);
}

skip_emoticons_loading:

echo $twig->render("my-emoticons.html.twig", [
   "list_css_files" => ["pool", "emoticons", "tab_system", "drag_and_drop_upload"],
   "list_js_files" => [["file" => "form_validation"], "drag_n_drop_upload", "paste_clipboard_media"],
   "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   "flash_message_extra_data" => isset($_COOKIE['flash_message_extra_data']) ? json_decode($_COOKIE['flash_message_extra_data']) : "",
   "selectedLogo" => "default",
   "page_title" => "Mes émoticônes",
   "list_emojis" => $listEmoticons,
   "meta" => [
      ...$twig->getGlobals()["meta"],
      "title" => "Mes émoticônes",
      "description" => "Critiques et chroniques sur le jeu vidéo par des passionnés",
      "image" => "https://" . $_SERVER["HTTP_HOST"] . "/default_meta_logo.jpg",
      "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
      "full_title" => "",
   ]
]);

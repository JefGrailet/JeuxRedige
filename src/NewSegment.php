<?php

/*
* Script to create a segment, i.e., a page of a full article (which can be made of several
* segments or a single segment). As long as the current user is the author of the article and that
* this article is not published (yet), (s)he can create a new segment for it.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './libraries/Buffer.lib.php';
require './model/Article.class.php';
require './model/Segment.class.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

// Errors where the user is either not logged in, either not allowed to edit games
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

$curlUpload = function ($file) use ($twig) {
   $useragent = $_SERVER['HTTP_USER_AGENT'];
   $strCookie = 'PHPSESSID=' . $_COOKIE['PHPSESSID'] . '; path=/';

   session_write_close();

   $curl = curl_init();
   curl_setopt($curl, CURLOPT_URL, $twig->getGlobals()["webRoot"] . '/ajax/CreateSegmentHeader.php?mode=no_path');
   curl_setopt($curl, CURLOPT_VERBOSE, 1);

   curl_setopt($curl, CURLOPT_POST, true);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

   curl_setopt(
      $curl,
      CURLOPT_POSTFIELDS,
      [
         'image' => new CurlFile(
            $file["tmp_name"],
            $file["type"],
            $file["name"],
         ),
      ]);
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($curl,CURLOPT_USERAGENT, $useragent);
   curl_setopt($curl, CURLOPT_COOKIE, $strCookie);

   $result = curl_exec($curl);
   curl_close($curl);

   return $result;
};

$imageHeaderMaxSize = 1048576;
$imageHeaderRequirements = [
   "mimeTypes" => ["image/jpeg", "image/jpg"],
   "maxSize" => $imageHeaderMaxSize,
];

// Obtains article ID and retrieves the corresponding entry
if(!empty($_GET['id_article']) && preg_match('#^([0-9]+)$#', $_GET['id_article']))
{
   $articleID = intval(Utils::secure($_GET['id_article']));
   $article = null;
   $nextPosition = 0;
   try
   {
      $article = new Article($articleID);
      $nextPosition = $article->nextSegmentPosition();
   }
   catch(Exception $e)
   {
      http_response_code(404);
      echo $twig->render("error.html.twig", [
         "error_title" => "Page inaccessible",
         "error_key" => "nonexistingArticle",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur - Page inaccessible",
            "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "full_title" => "",
         ]
      ]);

      die();
   }

   // Can only create a new segment for one's own articles
   if(!$article->isMine())
   {
      http_response_code(401);
      echo $twig->render("error.html.twig", [
         "error_title" => "Page inaccessible",
         "error_key" => "notYours",
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur - Page inaccessible",
            "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "full_title" => "",
         ]
      ]);

      die();
   }

   // Webpage settings
   WebpageHandler::addCSS('preview');
   WebpageHandler::addCSS('article_edition'); // Put here to override some values of preview.css
   WebpageHandler::addJS('preview');
   WebpageHandler::addJS('formatting');
   WebpageHandler::addJS('segment_editor');
   WebpageHandler::changeContainer('fullWidthSequence');

   // Dialogs
   $dialogs = '';
   // if(Utils::check(LoggedUser::$data['can_upload']))
   // {
   //    $headerDialogTpl = TemplateEngine::parse('view/dialog/CreateSegmentHeader.dialog.ctpl');
   //    if(!TemplateEngine::hasFailed($headerDialogTpl))
   //       $dialogs .= $headerDialogTpl;
   //    $fileUploadDialogTpl = TemplateEngine::parse('view/dialog/UploadFile.dialog.ctpl');
   //    if(!TemplateEngine::hasFailed($fileUploadDialogTpl))
   //       $dialogs .= $fileUploadDialogTpl;
   // }
   // $formattingDialogsTpl = TemplateEngine::parse('view/dialog/Formatting.multiple.ctpl');
   // if(!TemplateEngine::hasFailed($formattingDialogsTpl))
   //    $dialogs .= $formattingDialogsTpl;
   // $eFormattingDialogsTpl = TemplateEngine::parse('view/dialog/ExtendedFormatting.multiple.ctpl');
   // if(!TemplateEngine::hasFailed($eFormattingDialogsTpl))
   //    $dialogs .= $eFormattingDialogsTpl;

   // Header details (default image or buffered image)
   $currentSegmentHeader = Buffer::getSegmentHeader();
   $currentHeaderValue = '';
   if(strlen($currentSegmentHeader) == 0)
      $currentSegmentHeader = './default_article_header.jpg';
   else
      $currentHeaderValue = './'.substr($currentSegmentHeader, strlen(PathHandler::HTTP_PATH()));

   // $formData = array('errors' => '',
   // 'articleID' => $article->get('id_article'),
   // 'fullArticleTitle' => $article->get('title').' - '.$article->get('subtitle'),
   // 'headerPath' => $currentSegmentHeader,
   // 'title' => '',
   // 'noteFirstSegment' => ($nextPosition == 1) ? 'yes' : '',
   // 'content' => '',
   // 'header' => $currentHeaderValue,
   // 'mediaMenu' => '');

   // Generates upload window view
   $nbUploads = 0; // Useful later
   // if(Utils::check(LoggedUser::$data['can_upload']))
   // {
   //    $uploadsList = Buffer::listContent();
   //    $nbUploads = count($uploadsList[0]);

   //    $uploadTplInput = array('uploadMessage' => 'newUpload', 'uploadsView' => Buffer::renderForSegment($uploadsList));
   //    $uploadTpl = TemplateEngine::parse('view/user/NewSegment.upload.ctpl', $uploadTplInput);

   //    if(!TemplateEngine::hasFailed($uploadTpl))
   //       $formData['mediaMenu'] = $uploadTpl;
   // }
   // else
   // {
   //    $uploadTplInput = array('uploadMessage' => 'uploadRefused', 'uploadsView' => '');
   //    $uploadTpl = TemplateEngine::parse('view/user/NewSegment.upload.ctpl', $uploadTplInput);

   //    if(!TemplateEngine::hasFailed($uploadTpl))
   //       $formData['mediaMenu'] = $uploadTpl;
   // }

   $formErrorMessages = $twig->getGlobals()["errors_message"]["page"];
   $formErrorMessagesTriggered = [];
   // Form treatment starts here
   if(!empty($_POST)) {
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['content'] = Utils::secure($_POST['content']);
      $formData['header'] = Utils::secure($_POST['header']);
   }

   print_r($article);
   print_r($article->get("type"));

   echo $twig->render("add_edit_article-page.html.twig", [
      "page_title" => "\"{$article->get("title")}\" - Ajouter une nouvelle page",
      "list_css_files" => [ "input_file", "badge", "drag_and_drop_upload"],
      "type" => "add",
      "list_js_files" => [
         ["file" => "form_validation"],
         "upload",
         "drag_n_drop_upload",
         "paste_clipboard_media",
      ],
      "article" => [
         ...$article->getAll(),
      ],
      "currentCategory" => $article->get("type"),
      "page" => [],
      "image_header_requirements" => [
         ...$imageHeaderRequirements,
         "mimeTypes" => join(",", $imageHeaderRequirements["mimeTypes"])
      ],
      "form_error_messages" => $formErrorMessages,
      "form_error_messages_triggered" => $formErrorMessagesTriggered,
      "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   ]);
}
else
{
   $tplInput = array('error' => 'missingArticleID');
   $tpl = TemplateEngine::parse('view/user/EditSegment.fail.ctpl', $tplInput);
   WebpageHandler::wrap($tpl, 'Une erreur est survenue');
}

?>

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
   curl_setopt($curl, CURLOPT_URL, $twig->getGlobals()["webRoot"] . '/ajax/CreateSegmentHeader.php');
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
   if(!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts']))
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

   // LoggedUser::$data['can_upload']

   // Header details (default image or buffered image)
   $currentSegmentHeader = Buffer::getSegmentHeader();
   $currentHeaderValue = '';
   if(strlen($currentSegmentHeader || "") == 0)
      $currentSegmentHeader = './default_article_header.jpg';
   else
      $currentHeaderValue = './'.substr($currentSegmentHeader, strlen(PathHandler::HTTP_PATH()));


   $formErrorMessages = $twig->getGlobals()["error_messages"];
   $formErrorMessagesTriggered = [];
   // Form treatment starts here
   if(!empty($_POST)) {
      $formData = [];
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['content'] = Utils::secure($_POST['content']);

      $curlResult = null;
      if ($_FILES["header"]["size"] > 0) {
         $curlResult = $curlUpload($_FILES["header"]);
      }

      if (($nextPosition > 1 && empty($formData['title'])) || empty($formData['content']))
         array_push($formErrorMessagesTriggered, $formErrorMessages["emptyFields"]);
      if (in_array($curlResult, array_keys($formErrorMessages["page"]["header"])))
         array_push($formErrorMessagesTriggered, $formErrorMessages["page"]["header"][$curlResult]);

      if (count($formErrorMessagesTriggered) == 0) {
         try {
            $newSeg = Segment::insert(
               $article->get('id_article'),
               $formData['title'],
               $nextPosition,
               FormParsing::parse($formData['content'])
            );

            $uploads = Buffer::listContent();
            if (count($uploads[0]) > 0) {
               $uploadsString = Buffer::saveInSegment(
                  $uploads,
                  $article->get('id_article'),
                  $newSeg->get('id_segment')
               );

               if (strlen($uploadsString) > 0) {
                  $modifiedContent = FormParsing::relocateInSegment(
                     $newSeg->get('content'),
                     $article->get('id_article'),
                     $newSeg->get('id_segment')
                  );

                  $newSeg->finalize('uploads:' . $uploadsString, $modifiedContent);
               }
            }

            if (is_null($curlResult) === false)
            {
               $fileName = basename($curlResult);
               Buffer::save('upload/articles/'.$article->get('id_article').'/'.$newSeg->get('id_segment'), $fileName, 'header');
            }

            if (isset($_POST["action"]) && $_POST["action"] === "preview") {
               $redirectURL = PathHandler::articleURL($article->getAll(), $newSeg->get('position'));
            } else {
               $redirectURL = './EditArticle.php?id_article=' . $article->get('id_article');
            }

            setcookie("flash_message", "page_created", time() + 1, "/");
            exit(header('Location:' . $redirectURL));
         } catch(Exception $e) {

         }
      }
   }

   echo $twig->render("add_edit_article-page.html.twig", [
      "page_title" => "\"{$article->get("title")}\" - Ajouter une nouvelle page",
      "list_css_files" => [ "article", "input_file", "badge", "drag_and_drop_upload", "text_editor_toolbar" ],
      "type" => "add",
      "list_js_files" => [
         "segment_editor",
         "formatting",
         ["file" => "form_validation"],
         "drag_n_drop_upload",
         "paste_clipboard_media",
         "modal_delete_media",
         "modals_page",
         "preview2",
      ],
      "article" => [
         ...$article->getAll()
      ],
      "currentCategory" => $article->get("type"),
      "page" => [
         "position" => $nextPosition,
      ],
      "image_header_requirements" => [
         ...$imageHeaderRequirements,
         "mimeTypes" => join(",", $imageHeaderRequirements["mimeTypes"])
      ],
      "form_error_messages" => $formErrorMessages["page"],
      "form_error_messages_triggered" => $formErrorMessagesTriggered,
      "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   ]);
}
else
{
   echo $twig->render("error.html.twig", [
      "error_title" => "Une erreur est survenue",
      "error_key" => "",
      "meta" => [
         ...$twig->getGlobals()["meta"],
         "title" => "Erreur",
         "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
         "full_title" => "",
      ]
   ]);
}


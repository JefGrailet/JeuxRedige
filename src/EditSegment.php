<?php

/*
* Script to edit a segment, i.e., a page of a full article (which can be made of several segments
* or a single segment). As long as the current user is the author of the article, (s)he can edit
* any of its segments.
*/

require './libraries/Header.lib.php';
require './libraries/FormParsing.lib.php';
require './libraries/Buffer.lib.php';
require './model/Article.class.php';
require './model/Segment.class.php';

require_once './libraries/core/Twig.config.php';

WebpageHandler::redirectionAtLoggingIn();

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
      ]
   );
   curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
   curl_setopt($curl, CURLOPT_COOKIE, $strCookie);

   $result = curl_exec($curl);

   return $result;
};

// Errors where the user is either not logged in, either not allowed to edit games
if (!LoggedUser::isLoggedIn()) {
   http_response_code(401);
   echo $twig->render("errors/error.html.twig", [
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

$imageHeaderMaxSize = 1048576;
$imageHeaderRequirements = [
   "mimeTypes" => ["image/jpeg", "image/jpg"],
   "maxSize" => $imageHeaderMaxSize,
];

if (!empty($_GET['id_segment']) && preg_match('#^([0-9]+)$#', $_GET['id_segment'])) {
   $segmentID = intval(Utils::secure($_GET['id_segment']));
   $segment = null;
   $article = null;
   try {
      $segment = new Segment($segmentID);
      $article = new Article($segment->get('id_article'));
   } catch (Exception $e) {
      $tplInput = array('error' => 'dbError');
      $errorPageTitle = '';
      if (strstr($e->getMessage(), 'does not exist') != FALSE) {
         $errorKey = "";
         if (strstr($e->getMessage(), 'Segment') != FALSE) {
            $errorKey = 'nonexistingSegment';
         } else {
            $errorKey = 'nonexistingArticle';
         }
      }

      http_response_code(404);
      echo $twig->render("errors/error.html.twig", [
         "error_title" => "Page inaccessible",
         "error_key" => $errorKey,
         "meta" => [
            ...$twig->getGlobals()["meta"],
            "title" => "Erreur - Page inaccessible",
            "url" => "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"],
            "full_title" => "",
         ]
      ]);

      die();
   }

   // Forbidden access if the user's neither the author, neither an admin
   if (!$article->isMine() && !Utils::check(LoggedUser::$data['can_edit_all_posts'])) {
      http_response_code(401);
      echo $twig->render("errors/error.html.twig", [
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

   $formErrorMessages = $twig->getGlobals()["error_messages"];
   $formErrorMessagesTriggered = [];

   $listPageAttachements = [];
   $listPageAttachementsFormatted = [];
   if ($segment->get('attachment') !== NULL && strlen($segment->get('attachment')) > 0)
      $listPageAttachements = explode('|', $segment->get('attachment'));

   $existingUploads = '';
   $nbExistingUploads = 0;

   for ($i = 0; $i < count($listPageAttachements); $i++) {
      if (substr($listPageAttachements[$i], 0, 7) === 'uploads') {
         $splitted = explode(':', $listPageAttachements[$i]);
         $uploads = explode(',', $splitted[1]);
         $nbExistingUploads = count($uploads);

         $listPageAttachementsFormatted = Buffer::listMiniatures($uploads)[0];

         if ($nbExistingUploads === 0)
            break;
         break;
      }
   }

   $listPageAttachementsFormatted = array_map(function ($filename) use ($article, $segment) {
      $httpPathPrefix = PathHandler::HTTP_PATH() . 'upload';
      $wwwPathPrefix = PathHandler::WWW_PATH() . 'upload';

      $segmentID = $segment->get('id_segment');
      $articleID = $article->get('id_article');

      $ext = pathinfo($filename, PATHINFO_EXTENSION);
      $mimeType = mime_content_type($wwwPathPrefix . "/articles/$articleID/$segmentID/{$filename}");

      if(in_array($ext, Utils::UPLOAD_OPTIONS['miniExtensions']))
      {
         $realFilename = explode("full_", $filename)[1];

         $sizeFull = getimagesize($wwwPathPrefix . "/articles/$articleID/$segmentID/full_{$realFilename}");
         $sizeMini = getimagesize($wwwPathPrefix . "/articles/{$articleID}/{$segmentID}/mini_{$realFilename}");

         return [
            "mini" => [
               "src" => "{$httpPathPrefix}/articles/$articleID/$segmentID/mini_{$realFilename}",
               "size" => [
                  "width" => $sizeMini[0],
                  "height" => $sizeMini[1],
               ]
            ],
            "full" => [
               "src" => "{$httpPathPrefix}/articles/{$articleID}/{$segmentID}/full_{$realFilename}",
               "size" => [
                  "width" => $sizeFull[0],
                  "height" => $sizeFull[1],
               ],
               "srcRelative" => "upload/articles/{$articleID}/{$segmentID}/full_{$realFilename}"
            ],
            "mediaType" => "image",
            "mimeType" => $mimeType,
            "uploadDate" => date('d/m/Y à H:i:s', filemtime($wwwPathPrefix . "/articles/{$articleID}/{$segmentID}/full_{$realFilename}")),
            "id" => uniqid(),
            "filename" => $realFilename,
         ];
      }

      return [
         "full" => [
            "src" => "{$httpPathPrefix}/articles/{$articleID}/{$segmentID}/{$filename}",
            "srcRelative" => "upload/articles/{$articleID}/{$segmentID}/{$filename}",
            "size" =>  []
         ],
         "mediaType" => "video",
         "mimeType" => $mimeType,
         "uploadDate" => date('d/m/Y à H:i:s', filemtime($wwwPathPrefix . "/articles/{$articleID}/{$segmentID}/{$filename}")),
         "id" => uniqid(),
         "filename" => $filename,
      ];
   }, $listPageAttachementsFormatted);

   if (!empty($_POST)) {
      $formData = [];
      $formData['title'] = Utils::secure($_POST['title']);
      $formData['content'] = Utils::secure($_POST['content']);

      $curlResult = null;
      if ($_FILES["header"]["size"] > 0) {
         $curlResult = $curlUpload($_FILES["header"]);
      }

      if (($segment->get('position') > 1 && empty($formData['title'])) || empty($formData['content']))
         array_push($formErrorMessagesTriggered, $formErrorMessages["emptyFields"]);
      if (in_array($curlResult, array_keys($formErrorMessages["segment"]["header"])))
         array_push($formErrorMessagesTriggered, $formErrorMessages["segment"]["header"][$curlResult]);

      if (count($formErrorMessagesTriggered) == 0) {
         Database::beginTransaction();
         try {
            // Option for admins to not record date of editionForm
            $doNotRecordDateUpdate = false;
            if (Utils::check(LoggedUser::$data['can_edit_all_posts']) && isset($_POST['do_not_record'])) {
               $doNotRecordDateUpdate = true;
            }

            $segment->update(
               $formData['title'],
               FormParsing::parse($formData['content']),
               ($article->isPublished() && !$doNotRecordDateUpdate)
            );

            if (!$doNotRecordDateUpdate)
               $article->recordDate($segment->get('date_last_modification'));

            $uploads = Buffer::listContent();
            if (count($uploads[0]) > 0 && $nbExistingUploads < Utils::UPLOAD_OPTIONS['bufferLimit']) {
               $modifiedContent = FormParsing::relocateInSegment(
                  $segment->get('content'),
                  $article->get('id_article'),
                  $segment->get('id_segment')
               );

               $maxUploads = Utils::UPLOAD_OPTIONS['bufferLimit'] - $nbExistingUploads;
               $newUploadsString = Buffer::saveInSegment(
                  $uploads,
                  $article->get('id_article'),
                  $segment->get('id_segment'),
                  $maxUploads
               );

               $newUploadsFull = '';
               if (strlen($newUploadsString) > 0 && $nbExistingUploads > 0) {
                  for ($i = 0; $i < count($listPageAttachements); $i++) {
                     if (substr($listPageAttachements[$i], 0, 7) === 'uploads') {
                        $listPageAttachements[$i] .= ',' . $newUploadsString;
                        $newUploadsFull = explode(',', substr($listPageAttachements[$i], 8));
                        break;
                     }
                  }

                  $segment->finalize(implode('|', $listPageAttachements), $modifiedContent);
               } else if (strlen($newUploadsString) > 0) {
                  array_push($listPageAttachements, 'uploads:' . $newUploadsString);
                  $newUploadsFull = explode(',', $newUploadsString);

                  $segment->finalize(implode('|', $listPageAttachements), $modifiedContent);
               }
            }
            Database::commit();

            if (is_null($curlResult) === false)
            {
               $fileName = basename($curlResult);
               Buffer::save('upload/articles/'.$article->get('id_article').'/'.$segment->get('id_segment'), $fileName, 'header');
            }

            if (isset($_POST["action"]) && $_POST["action"] === "preview") {
               $redirectURL = PathHandler::articleURL($article->getAll(), $segment->get('position'));
            } else {
               $redirectURL = './EditSegment.php?id_segment=' . $segment->get('id_segment');
            }

            setcookie("flash_message", "page_updated", time() + 1, "/");
            exit(header('Location:' . $redirectURL));
         } catch (Exception $e) {
            array_push($formErrorMessagesTriggered, $formErrorMessages["dbError"]);
         }
      }
   }

   $pageHeader = $segment->getHeader();

   if (empty($pageHeader)) {
      $pageHeader = './default_article_header.jpg';
   }

   echo $twig->render("add_edit_article-page.html.twig", [
      "page_title" => "\"{$article->get("title")}\" - Éditer la page \"{$segment->get("title")}\"",
      "list_css_files" => ["article", "input_file", "badge", "drag_and_drop_upload", "text_editor_toolbar"],
      "type" => "add",
      "list_js_files" => [
         "segment_editor",
         "formatting",
         ["file" => "form_validation"],
         "drag_n_drop_upload",
         "paste_clipboard_media",
         "modals_page",
         "preview2",
      ],
      "type" => "edit",
      "article" => [
         ...$article->getAll(),
      ],
      "current_category" => $article->get('type'),
      "page" => [
         ...$segment->getAll(),
         "content" => FormParsing::unparse($segment->get('content')),
         "list_attachments" => $listPageAttachementsFormatted,
         "header" => $pageHeader,
      ],
      "currentCategory" => $article->get("type"),
      "image_header_requirements" => [
         ...$imageHeaderRequirements,
         "mimeTypes" => join(",", $imageHeaderRequirements["mimeTypes"])
      ],
      "form_error_messages" => $formErrorMessages,
      "form_error_messages_triggered" => $formErrorMessagesTriggered,
      "flash_message" => isset($_COOKIE['flash_message']) ? $_COOKIE['flash_message'] : "",
   ]);
} else {
   echo $twig->render("errors/error.html.twig", [
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
